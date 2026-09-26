<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Genre extends Model
{
    protected $fillable = ['name', 'slug'];

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }
}
