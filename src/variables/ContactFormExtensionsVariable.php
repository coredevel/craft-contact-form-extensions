<?php
/**
 * schema plugin for Craft CMS 4.x.
 *
 * A fluent builder Schema.org types and ld+json generator based on Spatie's schema-org package
 */

namespace hybridinteractive\contactformextensions\variables;

use Craft;
use craft\elements\db\ElementQueryInterface;
use hybridinteractive\contactformextensions\ContactFormExtensions;
use hybridinteractive\contactformextensions\elements\Submission;

class ContactFormExtensionsVariable
{
    public function name()
    {
        return ContactFormExtensions::$plugin->name;
    }

    public function recaptcha(?string $localeOrAction = null)
    {
        if (ContactFormExtensions::$plugin->settings->recaptcha) {
            return ContactFormExtensions::$plugin->contactFormExtensionsService->getRecaptcha()->render($localeOrAction);
        }

        return '';
    }

    public function submissions(array $criteria = []): ElementQueryInterface
    {
        $query = Submission::find();

        // Apply any passed criteria (form, subject, fromEmail, etc.)
        if (!empty($criteria)) {
            Craft::configure($query, $criteria);
        }

        // Sensible default ordering: newest first
        if ($query->orderBy === null) {
            $query->orderBy(['elements.dateCreated' => SORT_DESC]);
        }

        // Safety net: if no limit specified, default to something sane
        if ($query->limit === null) {
            $query->limit(50);
        }

        return $query;
    }
}
