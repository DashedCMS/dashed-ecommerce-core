<div>
    @if ($ownerRecord->type == 'variable' && !$ownerRecord->parent_product_id)
        {{ $this->table }}
    @endif
</div>
