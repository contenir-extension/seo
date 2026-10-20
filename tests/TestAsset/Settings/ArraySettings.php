<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\TestAsset\Settings;

use Contenir\Extension\Api\Event\ActionResult;
use Contenir\Extension\Api\Event\Outcome;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Settings\SettingsInterface;
use Contenir\Extension\Api\Settings\SettingsSection;
use Override;

use function array_key_exists;

/**
 * One section's settings held in an array: saved values over the declared
 * defaults, as a host reads them. set() records what it saved and answers
 * with the outcome it was given.
 */
final class ArraySettings implements SettingsInterface
{
    /**
     * @var array<string, mixed>
     */
    public array $saved = [];

    /**
     * @param array<string, mixed> $values By field name
     */
    public function __construct(
        private readonly SettingsSection $section,
        private array $values = [],
        private readonly Outcome $outcome = Outcome::Done,
        private readonly string $message = '',
    ) {}

    #[Override]
    public function all(): array
    {
        $defaults = [];
        foreach ($this->section->form->fields as $field) {
            $defaults[$field->name] = $field->default;
        }

        return [...$defaults, ...$this->values];
    }

    #[Override]
    public function get(string $field, mixed $default = null): mixed
    {
        $all = $this->all();
        if (! array_key_exists($field, $all)) {
            throw UnknownSettingException::field($this->section->name, $field);
        }

        return $all[$field] ?? $default;
    }

    #[Override]
    public function section(): string
    {
        return $this->section->name;
    }

    #[Override]
    public function set(array $values): ActionResult
    {
        $this->saved = [...$this->saved, ...$values];
        if (Outcome::Done === $this->outcome) {
            $this->values = [...$this->values, ...$values];
        }

        return new ActionResult($this->outcome, null, $this->message);
    }
}
