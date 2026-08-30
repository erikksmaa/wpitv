@props(['node', 'tvClub' => false])

@php($position = $node['position'])
<li class="public-org-tree__branch">
    <article class="public-org-node">
        <span class="public-org-node__accent" aria-hidden="true"></span>
        <small>{{ $tvClub ? 'TV Club' : 'WPiTV' }}</small>
        <h3>{{ $position->name }}</h3>

        <div class="public-org-node__people">
            @if($tvClub)
                @forelse($position->assignments as $assignment)
                    <span>
                        <b class="public-org-avatar">
                            @if($assignment->member->photo_url)
                                <img src="{{ $assignment->member->photo_url }}" alt="" loading="lazy">
                            @else
                                {{ $assignment->member->initials }}
                            @endif
                        </b>
                        <strong>{{ $assignment->member->name }}</strong>
                    </span>
                @empty
                    <em>Belum ditetapkan</em>
                @endforelse
            @else
                @if($position->user)
                    <span><b class="public-org-avatar">{{ $position->user->initials }}</b><strong>{{ $position->user->name }}</strong></span>
                @else
                    <em>Belum ditetapkan</em>
                @endif
            @endif
        </div>
    </article>

    @if($node['children'])
        <ul class="public-org-tree__children">
            @foreach($node['children'] as $child)
                <x-public.organization-tree-node :node="$child" :tv-club="$tvClub" />
            @endforeach
        </ul>
    @endif
</li>
