<?php

namespace Dashed\DashedEcommerceCore\Support;

use Closure;
use Throwable;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\BackgroundTask;

/**
 * Meldt werk op de achtergrond aan bij het takenicoon van dashed-core. Een
 * oudere dashed-core kent het model niet: dan doet alles hier niets. Niets
 * hier mag het werk zelf tegenhouden, dus elke aanroep loopt door rescue().
 */
class BackgroundTasks
{
    public static function available(): bool
    {
        return class_exists(BackgroundTask::class);
    }

    /**
     * Start een taak. Met een sleutel (het uuid van de job) hergebruikt een
     * nieuwe poging van dezelfde job zijn eigen rij in plaats van er een bij
     * te maken.
     */
    public static function start(string $type, string $label, ?int $total = null, ?int $userId = null, ?string $key = null): ?BackgroundTask
    {
        if (! static::available()) {
            return null;
        }

        return rescue(function () use ($type, $label, $total, $userId, $key) {
            if ($key === null) {
                return BackgroundTask::begin($type, $label, $total, $userId, notify: $userId !== null);
            }

            $task = BackgroundTask::query()->firstOrNew([
                'subject_type' => $type,
                'subject_id' => $key,
            ]);

            $task->fill([
                'type' => $type,
                'label' => $label,
                'user_id' => $userId,
                'status' => BackgroundTask::STATUS_RUNNING,
                'done' => 0,
                'total' => $total,
                'notify' => $userId !== null,
                'message' => null,
                'finished_at' => null,
            ]);
            $task->started_at ??= now();
            $task->save();

            return $task;
        }, null, report: false);
    }

    /**
     * Zet de lopende taak van een afgebroken job op mislukt. In failed() is
     * de taak uit handle() niet bekend: zoek hem op de sleutel, anders de
     * laatste lopende van dit type (en deze gebruiker).
     */
    public static function fail(string $type, Throwable|string|null $error = null, ?string $key = null, ?int $userId = null): void
    {
        if (! static::available()) {
            return;
        }

        rescue(function () use ($type, $error, $key, $userId) {
            $query = BackgroundTask::query()->where('type', $type)->active();

            if ($key !== null) {
                $query->where('subject_type', $type)->where('subject_id', $key);
            } elseif ($userId !== null) {
                $query->where('user_id', $userId);
            }

            $query->latest('id')->first()?->fail($error);
        }, report: false);
    }

    /**
     * Eén taak voor een actie die uitwaaiert in een job per item. Geeft het
     * id terug om aan elke job mee te geven.
     */
    public static function startBatch(string $type, string $label, int $total, ?int $userId = null): ?int
    {
        $id = static::start($type, $label, $total, $userId)?->getKey();

        return $id ? (int) $id : null;
    }

    /**
     * Telt één afgeronde job van een batch. Het ophogen gebeurt in de
     * database zelf, want meerdere workers werken tegelijk aan dezelfde
     * batch. De job die het totaal bereikt sluit de taak af.
     *
     * @param  Closure(int $gelukt, int $mislukt): string  $message
     */
    public static function advanceBatch(?int $taskId, Closure $message, bool $failed = false): void
    {
        if (! $taskId || ! static::available()) {
            return;
        }

        rescue(function () use ($taskId, $message, $failed) {
            $failedKey = 'dashed-ecommerce-core.background-task-failed.' . $taskId;

            if ($failed) {
                Cache::add($failedKey, 0, now()->addDay());
                Cache::increment($failedKey);
            }

            BackgroundTask::query()->whereKey($taskId)->increment('done');

            $task = BackgroundTask::query()->find($taskId);
            if (! $task || $task->isFinished() || ! $task->total || $task->done < $task->total) {
                return;
            }

            // Alleen de worker die de status hier omzet sluit af, anders
            // krijgt de beheerder de eindmelding twee keer.
            $claimed = BackgroundTask::query()
                ->whereKey($taskId)
                ->where('status', BackgroundTask::STATUS_RUNNING)
                ->update(['status' => BackgroundTask::STATUS_COMPLETED]);

            if (! $claimed) {
                return;
            }

            $failedCount = (int) Cache::pull($failedKey, 0);
            $text = $message(max(0, $task->done - $failedCount), $failedCount);

            if ($failedCount > 0 && $failedCount >= $task->done) {
                $task->fail($text);

                return;
            }

            $task->finish($text);
        }, report: false);
    }
}
