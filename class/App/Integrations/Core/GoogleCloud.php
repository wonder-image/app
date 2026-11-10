<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;
use Wonder\App\Integrations\IntegrationStatus;
use Wonder\App\Support\GoogleCloud\GoogleApis;
use Wonder\Elements\Components\Accordion;
use Wonder\Elements\Components\Text;

/** Progetto e chiavi Google Cloud: servono a reCAPTCHA, Maps e Accedi con Google. */
final class GoogleCloud extends Integration
{
    public function key(): string
    {
        return 'gcp';
    }

    public function name(): string
    {
        return 'Google Cloud Platform';
    }

    public function category(): string
    {
        return 'google';
    }

    public function icon(): string
    {
        return 'bi-google';
    }

    public function description(): string
    {
        return 'Progetto e chiavi API di Google.';
    }

    public function fields(): array
    {
        return [
            'gcp_project_id' => 12,
            'gcp_api_key' => 6,
            'gcp_client_api_key' => 6,
        ];
    }

    public function order(): int
    {
        return 20;
    }

    public function docsUrl(): ?string
    {
        return 'https://wonder-image.gitbook.io/app/altro/servizi/google-cloud-platform';
    }

    /** Le API da abilitare nel progetto, con il link alla console. */
    public function panel(array $values, IntegrationStatus $status): array
    {
        $projectId = (string) ($values['gcp_project_id'] ?? '');
        $rows = [Text::make(__t('google.credentials.intro'))->muted()->columnSpan(12)];

        foreach (GoogleApis::all() as $api) {
            $rows[] = Text::make()
                ->link($api->consoleUrl($projectId), $api->getName(), ['blank' => true])
                ->append(' · '.__t('google.credentials.key_types.'.$api->getKeyType()))
                ->append(' · '.__t($api->isRequired() ? 'google.credentials.required' : 'google.credentials.optional'))
                ->append(' · '.__t($api->getPurpose()))
                ->small()
                ->columnSpan(12);
        }

        return [
            Accordion::make(__t('google.credentials.title'))
                ->link()
                ->columns(12)
                ->columnSpan(12)
                ->components($rows),
        ];
    }
}
