<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An earlier definition of an egg (Pelican's own JSON export), kept before every update so it can be put back.
 *
 * @property int $id
 * @property int $egg_id
 * @property string $definition
 * @property \Illuminate\Support\Carbon $created_at
 */
final class EggBackup extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'simplify_egg_update_backups';

    protected $fillable = ['egg_id', 'definition'];
}
