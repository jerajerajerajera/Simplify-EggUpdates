<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What Egg Updates knows about one egg, keyed by egg id (an update can change the egg's uuid).
 *
 * @property int $egg_id
 * @property string|null $found_hash hash of a newer definition at the update URL, or null when the egg is current
 * @property \Illuminate\Support\Carbon|null $found_at
 * @property bool $held true after Put back: automatic updates leave the egg alone until it is updated by hand
 * @property string|null $applied_hash hash of the remote definition that was last installed
 */
final class EggUpdate extends Model
{
    protected $table = 'simplify_egg_updates';

    protected $primaryKey = 'egg_id';

    public $incrementing = false;

    protected $fillable = ['egg_id', 'found_hash', 'found_at', 'held', 'applied_hash'];

    protected function casts(): array
    {
        return [
            'found_at' => 'datetime',
            'held' => 'boolean',
        ];
    }

    public static function for(int $eggId): self
    {
        return self::query()->firstOrNew(['egg_id' => $eggId]);
    }
}
