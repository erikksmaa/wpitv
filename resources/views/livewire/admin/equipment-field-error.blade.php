@php($fieldMessage = $errors->first($field))
@if(!$fieldMessage && isset($nested))
    @php($fieldMessage = collect($errors->get($field.'.*'))->flatten()->first())
@endif
@if($fieldMessage)
    <div class="invalid-feedback d-block" role="alert">{{ $fieldMessage }}</div>
@endif
