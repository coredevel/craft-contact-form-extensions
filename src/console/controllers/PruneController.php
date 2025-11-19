<?php

namespace hybridinteractive\contactformextensions\console\controllers;

use Craft;
use craft\console\Controller;
use hybridinteractive\contactformextensions\ContactFormExtensions;
use hybridinteractive\contactformextensions\elements\Submission;
use hybridinteractive\contactformextensions\jobs\PruneSubmissionsJob;
use yii\console\ExitCode;

class PruneController extends Controller
{
    public $defaultAction = 'run';

    /**
     * Manually trigger a prune job (does not reschedule).
     *
     * @return int
     */
    public function actionRun(): int
    {
        $this->stdout("=== Contact Form Extensions - Prune Command ===\n");
        
        $settings = ContactFormExtensions::$plugin->getSettings();
        $days = $settings->pruneAfterDays ?? 0;
        
        $this->stdout("Settings retrieved:\n");
        $this->stdout("  - pruneAfterDays: {$days}\n");
        $this->stdout("  - Type: " . gettype($days) . "\n");

        if (!is_numeric($days) || $days <= 0) {
            $this->stdout("\n❌ Pruning is disabled or not configured (pruneAfterDays = {$days}).\n");
            $this->stdout("   To enable, go to Settings → Plugins → Contact Form Extensions\n");
            $this->stdout("   and set 'Auto-prune submissions after (days)' to a positive number.\n");
            return ExitCode::OK;
        }

        // Calculate cutoff date
        $now = new \DateTime();
        $cutoffDate = (new \DateTime())->modify("-{$days} days");
        
        $this->stdout("\nDate calculations:\n");
        $this->stdout("  - Current date: " . $now->format('Y-m-d H:i:s') . "\n");
        $this->stdout("  - Cutoff date: " . $cutoffDate->format('Y-m-d H:i:s') . "\n");
        $this->stdout("  - Will delete submissions created before: " . $cutoffDate->format('Y-m-d H:i:s') . "\n");

        // Count submissions to be pruned
        $this->stdout("\nQuerying submissions...\n");
        
        $query = Submission::find()
            ->dateCreated('< ' . $cutoffDate->format('Y-m-d H:i:s'))
            ->status(null);
        
        $count = $query->count();
        
        $this->stdout("  - Found {$count} submissions older than {$days} days\n");

        if ($count === 0) {
            $this->stdout("\n✓ No submissions to prune.\n");
            return ExitCode::OK;
        }

        // Queue the prune job
        $this->stdout("\nQueueing prune job...\n");
        
        Craft::$app->queue->push(new PruneSubmissionsJob([
            'reschedule' => false, // Don't reschedule when run manually
        ]));

        $this->stdout("✓ Queued prune job for {$count} submissions older than {$days} days.\n");
        $this->stdout("  The job will process in the background.\n");
        
        return ExitCode::OK;
    }
}
