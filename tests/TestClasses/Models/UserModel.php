<?php

namespace AustinW\UnionPaginator\Tests\TestClasses\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class UserModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function posts(): HasMany
    {
        return $this->hasMany(PostModel::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CommentModel::class);
    }

    public function isVerified(): Attribute
    {
        return Attribute::get(static fn () => true);
    }
}
