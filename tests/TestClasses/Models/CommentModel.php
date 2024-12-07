<?php

namespace AustinW\UnionPaginator\Tests\TestClasses\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommentModel extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(PostModel::class);
    }
}
