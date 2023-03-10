<div>
    @if ($ownerRecord->type == 'variable' && !$ownerRecord->parent_id)
        {{ $this->table }}
    @endif
</div>
