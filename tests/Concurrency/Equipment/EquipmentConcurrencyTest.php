<?php

namespace Tests\Concurrency\Equipment;

use App\Livewire\Admin\EquipmentLoans\Form;
use App\Models\EquipmentAuditEvent;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Models\InventoryUnit;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\EquipmentScenarioTestCase;

class EquipmentConcurrencyTest extends EquipmentScenarioTestCase
{
    private function job(string $operation, string $token, array $input, ?int $actor = null): array
    {
        return ['operation' => $operation, 'actor_id' => $actor ?? $this->actor()->id, 'token' => $token, 'input' => $input];
    }

    public function test_two_operators_create_distinct_codes_for_the_same_model(): void
    {
        $names = [$this->prefix.'-A', $this->prefix.'-B'];
        try {
            $jobs = array_map(fn ($name) => $this->job('create_product', $this->token(), ['name' => $name, 'category_id' => $this->category->id]), $names);
            $results = $this->race($jobs, 'inventory_categories', $this->category->id);
            $this->assertSame([true, true], array_column($results, 'ok'));
        } finally {
            $units = InventoryUnit::where('category_id', $this->category->id)->whereIn('name', $names)->get();
            foreach ($units as $unit) {
                $this->own('units', $unit->id);
            }
        }
        $this->assertCount(2, $units->pluck('inventory_code')->unique());
        foreach ($units as $unit) {
            $this->assertMatchesRegularExpression('/^SON-A6400-[0-9]+$/', $unit->inventory_code);
        }
    }

    private function oneWinner(array $results): void
    {
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertSame(0, $this->primary->transactionLevel());
    }

    public function test_simultaneous_checkout_has_one_commit_without_double_booking(): void
    {
        $input = $this->input([$this->units[0], $this->units[1]]);
        $result = $this->race([$this->job('checkout', $this->token(), $input), $this->job('checkout', $this->token(), $input)], 'inventory_units', $this->units[0]->id);
        $this->oneWinner($result);
        $this->assertSame(1, EquipmentLoan::whereIn('submission_token', $this->owned['tokens'])->count());
        $this->assertSame(2, EquipmentLoanItem::whereIn('inventory_unit_id', $this->owned['units'])->whereNotNull('active_unit_id')->count());
        $this->assertSame(1, EquipmentAuditEvent::whereIn('actor_id', $this->owned['users'])->where('event_type', 'checkout')->count());
    }

    public function test_two_quantity_reviews_compete_for_the_last_unit(): void
    {
        $unit = $this->units[0];
        $data = $this->input([$unit]);
        unset($data['items']);
        $jobs = [];
        foreach ([User::ROLE_SUPER_ADMIN, User::ROLE_PRODUCTION] as $role) {
            $actor = $this->actor($role);
            $form = Livewire::actingAs($actor)->test(Form::class)
                ->set('data', $data)->set('rows.0.product', $unit->id)->call('review')->assertHasNoErrors();
            $jobs[] = $this->job('checkout', $this->token(), [...$data, 'items' => $form->get('allocation')[0]], $actor->id);
        }
        $this->oneWinner($this->race($jobs, 'inventory_units', $unit->id));
        $this->assertSame(1, EquipmentLoanItem::where('active_unit_id', $unit->id)->count());
        $this->assertSame(1, EquipmentAuditEvent::whereIn('operation_token', $this->owned['tokens'])->count());
    }

    public function test_identical_checkout_token_is_one_transaction_for_two_workers(): void
    {
        $token = $this->token();
        $job = $this->job('checkout', $token, $this->input([$this->units[0]]));
        $result = $this->race([$job, $job], 'inventory_units', $this->units[0]->id);
        $this->assertTrue($result[0]['ok']);
        $this->assertTrue($result[1]['ok']);
        $this->assertSame($result[0]['id'], $result[1]['id']);
        $this->assertSame(1, EquipmentLoan::where('submission_token', $token)->count());
        $this->assertSame(1, EquipmentAuditEvent::where('operation_token', $token)->count());
    }

    public function test_checkout_token_payload_and_actor_conflicts_are_rejected(): void
    {
        foreach ([false, true] as $i => $actorConflict) {
            $unit = $this->units[$i];
            $token = $this->token();
            $input = $this->input([$unit]);
            $other = $actorConflict ? $input : [...$input, 'purpose' => 'Different synthetic purpose'];
            $result = $this->race([$this->job('checkout', $token, $input), $this->job('checkout', $token, $other, $actorConflict ? $this->actor(User::ROLE_SUPER_ADMIN)->id : null)], 'inventory_units', $unit->id);
            $this->oneWinner($result);
            $this->assertSame(1, EquipmentAuditEvent::where('operation_token', $token)->count());
        }
    }

    public function test_overlapping_partial_return_batches_are_atomic(): void
    {
        $loan = $this->checkout([$this->units[0], $this->units[1]]);
        $items = $loan->items()->orderBy('id')->get();
        $result = $this->race([$this->job('return', $this->token(), $this->returnInput($loan, $items->all())), $this->job('return', $this->token(), $this->returnInput($loan, [$items[0]]))], 'equipment_loans', $loan->id);
        $this->oneWinner($result);
        $this->assertSame(2, $loan->fresh()->version);
        $this->assertSame(1, $loan->auditEvents()->where('event_type', 'returned')->count());
        $this->assertSame($result[0]['ok'] ? 0 : 1, $loan->items()->whereNull('returned_at')->count());
    }

    public function test_nonoverlapping_partial_returns_still_serialize_header_versions(): void
    {
        $loan = $this->checkout([$this->units[0], $this->units[1]]);
        $items = $loan->items()->orderBy('id')->get();
        $result = $this->race([$this->job('return', $this->token(), $this->returnInput($loan, [$items[0]])), $this->job('return', $this->token(), $this->returnInput($loan, [$items[1]]))], 'equipment_loans', $loan->id);
        $this->oneWinner($result);
        $this->assertSame(1, $loan->items()->whereNull('returned_at')->count());
        $remaining = $loan->items()->whereNull('returned_at')->get()->all();
        $this->recordReturn($this->actor(), $this->token(), $this->returnInput($loan, $remaining));
        $this->assertSame(0, $loan->items()->whereNull('returned_at')->count());
    }

    public function test_identical_return_retry_has_one_receipt(): void
    {
        $loan = $this->checkout([$this->units[0]]);
        $token = $this->token();
        $job = $this->job('return', $token, $this->returnInput($loan, $loan->items->all()));
        $result = $this->race([$job, $job], 'equipment_loans', $loan->id);
        $this->assertTrue($result[0]['ok']);
        $this->assertTrue($result[1]['ok']);
        $this->assertSame($result[0]['id'], $result[1]['id']);
        $this->assertSame(1, EquipmentAuditEvent::where('operation_token', $token)->count());
        $this->assertSame(2, $loan->fresh()->version);
    }

    public function test_loss_against_return_cannot_partially_merge_operations(): void
    {
        $loan = $this->checkout([$this->units[0], $this->units[1]]);
        $item = $loan->items()->first();
        $result = $this->race([$this->job('loss', $this->token(), $this->lossInput($loan, [$item])), $this->job('return', $this->token(), $this->returnInput($loan, [$item]))], 'equipment_loans', $loan->id);
        $this->oneWinner($result);
        $item->refresh();
        if ($result[0]['ok']) {
            $this->assertNotNull($item->lost_at);
            $this->assertNull($item->returned_at);
            $this->assertNotNull($item->active_unit_id);
            $this->assertFalse($this->units[0]->fresh()->is_active);
        } else {
            $this->assertNull($item->lost_at);
            $this->assertNotNull($item->returned_at);
            $this->assertNull($item->active_unit_id);
        }
        $this->assertSame(1, $loan->auditEvents()->whereIn('event_type', ['lost', 'returned'])->count());
    }

    public function test_extension_against_return_preserves_due_history(): void
    {
        $loan = $this->checkout([$this->units[0]]);
        $initial = $loan->initial_due_at->format('Y-m-d H:i:s.u');
        $extension = ['loan_id' => $loan->id, 'expected_version' => 1, 'new_due_at' => now('Asia/Jakarta')->addDays(3)->format('Y-m-d\TH:i'), 'reason' => 'Synthetic extension'];
        $result = $this->race([$this->job('extension', $this->token(), $extension), $this->job('return', $this->token(), $this->returnInput($loan, $loan->items->all()))], 'equipment_loans', $loan->id);
        $this->oneWinner($result);
        $this->assertSame($initial, $loan->fresh()->initial_due_at->format('Y-m-d H:i:s.u'));
        $this->assertSame(1, $loan->auditEvents()->whereIn('event_type', ['extended', 'returned'])->count());
        $this->assertSame($result[0]['ok'] ? 1 : 0, $loan->items()->whereNull('returned_at')->count());
        if ($result[1]['ok']) {
            $this->assertSame($initial, $loan->items()->first()->due_at_on_return->format('Y-m-d H:i:s.u'));
        }
    }
}
