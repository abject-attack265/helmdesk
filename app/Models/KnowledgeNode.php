<?php

namespace App\Models;

use App\Enums\KnowledgeIndexingStrategy;
use App\Enums\KnowledgeNodeKind;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class KnowledgeNode extends Model
{
    use HasUlids, Searchable;

    protected $connection = 'sqlite_rag';

    protected $table = 'knowledge_nodes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'strategy' => KnowledgeIndexingStrategy::class,
            'kind' => KnowledgeNodeKind::class,
            'level' => 'integer',
            'byte_start' => 'integer',
            'byte_end' => 'integer',
            'token_count' => 'integer',
            'embedding_dim' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function toSearchableArray(): array
    {
        return [
            'content' => (string) $this->content,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return trim((string) $this->content) !== '';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
