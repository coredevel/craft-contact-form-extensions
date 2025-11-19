<?php

namespace hybridinteractive\contactformextensions\jobs;

use Craft;
use craft\queue\BaseJob;
use hybridinteractive\contactformextensions\ContactFormExtensions;
use hybridinteractive\contactformextensions\elements\Submission;

class PruneSubmissionsJob extends BaseJob
{
    /**
     * Whether to reschedule this job after completion
     */
    public bool $reschedule = true;

    public function execute($queue): void
    {
        // Always get days from settings to ensure consistency
        $settings = ContactFormExtensions::$plugin->getSettings();
        $days = $settings->pruneAfterDays;

        if ($days <= 0) {
            Craft::info('[CFE] Pruning skipped: pruneAfterDays is not configured', __METHOD__);
            return;
        }

        $cutoff = (new \DateTime())->modify("-{$days} days");

        /** @var \craft\elements\db\ElementQuery $query */
        $query = Submission::find()
            ->dateCreated('< ' . $cutoff->format('Y-m-d H:i:s'))
            ->status(null)
            ->siteId('*')
            ->limit(null);

        $total = $query->count();
        
        Craft::info("[CFE] Pruning {$total} submissions older than {$days} days", __METHOD__);

        if ($total > 0) {
            $i = 0;
            $elementsService = Craft::$app->getElements();

            foreach ($query->each(100) as $submission) {
                // Hard delete = true to permanently remove from database
                $elementsService->deleteElement($submission, true);
                $this->setProgress($queue, ++$i / $total);
            }
        }

        // Reschedule the job if enabled
        if ($this->reschedule) {
            // Recheck settings in case they changed
            $settings = ContactFormExtensions::$plugin->getSettings();
            
            if ($settings->enableDatabase && $settings->pruneAfterDays > 0) {
                // Schedule next run in 6 hours (21600 seconds)
                $delay = 21600;
                
                Craft::$app->queue->delay($delay)->push(new self([
                    'reschedule' => true,
                ]));
                
                Craft::info("[CFE] Rescheduled prune job to run in {$delay} seconds", __METHOD__);
            }
        }
    }

    protected function defaultDescription(): ?string
    {
        $settings = ContactFormExtensions::$plugin->getSettings();
        $days = $settings->pruneAfterDays ?? 30;
        
        return Craft::t('contact-form-extensions', 'Pruning form submissions older than {days} days', [
            'days' => $days,
        ]);
    }
}
