<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Settings\DTOs\SettingDefinition;
use App\Domain\Settings\Enums\SettingScope;
use App\Domain\Settings\Enums\SettingType;

/**
 * The registry of configurable settings, as data (ADR-033). Every external-API
 * credential or provider selection the platform uses is declared here with the
 * config/env key it defaults to, so it can be configured from within the system
 * with a safe, bounded surface. New integrations are enabled by adding an entry.
 */
final class SettingCatalog
{
    /**
     * @return list<SettingDefinition>
     */
    public static function all(): array
    {
        return [
            // ---- AI plane (a tenant may bring its own key/model) ----
            new SettingDefinition('ai.completion', 'ai', SettingScope::Both, SettingType::Select, 'ai.completion', 'AI completion provider', 'Which LLM provider serves completions.', ['fake', 'claude']),
            new SettingDefinition('ai.claude.api_key', 'ai', SettingScope::Both, SettingType::Secret, 'ai.claude.api_key', 'Anthropic API key'),
            new SettingDefinition('ai.claude.model', 'ai', SettingScope::Both, SettingType::String, 'ai.claude.model', 'Anthropic model'),
            new SettingDefinition('ai.embedding', 'ai', SettingScope::System, SettingType::Select, 'ai.embedding', 'Embedding provider', null, ['fake', 'http']),
            new SettingDefinition('ai.vector', 'ai', SettingScope::System, SettingType::Select, 'ai.vector', 'Vector index', null, ['database', 'qdrant']),

            // ---- Analytics extraction ----
            new SettingDefinition('analytics.capture', 'analytics', SettingScope::System, SettingType::Select, 'analytics.capture', 'Analytics capture mode', null, ['sync', 'queue']),
            new SettingDefinition('analytics.export', 'analytics', SettingScope::System, SettingType::Select, 'analytics.export', 'Analytics warehouse export', null, ['fake', 'clickhouse']),
            new SettingDefinition('analytics.clickhouse.endpoint', 'analytics', SettingScope::System, SettingType::String, 'analytics.clickhouse.endpoint', 'ClickHouse endpoint'),
            new SettingDefinition('analytics.clickhouse.password', 'analytics', SettingScope::System, SettingType::Secret, 'analytics.clickhouse.password', 'ClickHouse password'),

            // ---- Outbox event stream ----
            new SettingDefinition('outbox.stream', 'outbox', SettingScope::System, SettingType::Select, 'outbox.stream', 'Event stream publisher', null, ['null', 'log', 'kafka']),
            new SettingDefinition('outbox.kafka.rest_proxy', 'outbox', SettingScope::System, SettingType::String, 'outbox.kafka.rest_proxy', 'Kafka/Redpanda REST proxy URL'),

            // ---- Enterprise providers ----
            new SettingDefinition('enterprise.domain_verifier', 'enterprise', SettingScope::System, SettingType::Select, 'enterprise.domain_verifier', 'Custom-domain verifier', null, ['fake', 'dns']),
            new SettingDefinition('enterprise.identity_provider', 'enterprise', SettingScope::System, SettingType::Select, 'enterprise.identity_provider', 'SSO identity provider', null, ['fake', 'oidc']),
            new SettingDefinition('enterprise.domain_target', 'enterprise', SettingScope::System, SettingType::String, 'enterprise.domain_target', 'Custom-domain CNAME target'),
        ];
    }

    public static function find(string $key): ?SettingDefinition
    {
        foreach (self::all() as $definition) {
            if ($definition->key === $key) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Definitions configurable at the given scope (system or tenant).
     *
     * @return list<SettingDefinition>
     */
    public static function forScope(SettingScope $scope): array
    {
        return array_values(array_filter(
            self::all(),
            fn (SettingDefinition $definition): bool => $scope === SettingScope::System
                ? $definition->scope->allowsSystem()
                : $definition->scope->allowsTenant(),
        ));
    }
}
