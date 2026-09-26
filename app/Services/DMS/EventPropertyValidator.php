<?php

namespace App\Services\DMS;

use App\Enums\Core\DataTypesEnum;
use App\Models\DMS\EventProperty;
use App\Models\DMS\EventType;
use App\Services\DMS\Files\FileProperties;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Checks the property values an external system sent against the event's own definition -
 * the same definition the catalog handed out for the form. The form is not trusted to have
 * enforced it: a missing required value, an unknown name, a list value outside its options
 * or a value of the wrong type is rejected here.
 */
class EventPropertyValidator
{
    private const int MAX_TEXT_LENGTH = 1000;

    /**
     * @param array<int|string, mixed> $input either a list of {name, value} or a name => value map
     * @return array<int, array{Name: string, Value: string, DataType: string}> rows ready to store as document attributes
     *
     * @throws ValidationException
     */
    public function validate(EventType $event, array $input): array
    {
        $values = $this->toMap($input);
        $definitions = $event->properties->keyBy('Name');
        $errors = [];
        $rows = [];

        foreach (array_diff_key($values, $definitions->all()) as $name => $_) {
            $errors["properties.$name"][] = "'$name' is not a property of this event.";
        }

        /** @var EventProperty $definition */
        foreach ($definitions as $name => $definition) {
            $raw = $values[$name] ?? null;

            if ($raw === null || (is_string($raw) && trim($raw) === '')) {
                if ($definition->IsRequired) {
                    $errors["properties.$name"][] = "{$definition->Label} is required.";
                }

                continue;
            }

            try {
                $rows[] = [
                    'Name' => $name,
                    'Value' => $this->normalise($definition, $raw),
                    'DataType' => $definition->DataType->value,
                ];
            } catch (InvalidPropertyValue $e) {
                $errors["properties.$name"][] = "{$definition->Label} {$e->getMessage()}";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    /**
     * @param array<int|string, mixed> $input
     * @return array<string, mixed>
     */
    private function toMap(array $input): array
    {
        $map = [];
        foreach ($input as $key => $item) {
            if (is_array($item) && array_key_exists('name', $item)) {
                $map[(string) $item['name']] = $item['value'] ?? null;
            } elseif (is_string($key)) {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    /**
     * @throws InvalidPropertyValue
     */
    private function normalise(EventProperty $definition, mixed $raw): string
    {
        if (! is_scalar($raw)) {
            throw new InvalidPropertyValue('must be a single value.');
        }

        $value = is_bool($raw) ? ($raw ? '1' : '0') : trim((string) $raw);

        return match ($definition->DataType) {
            DataTypesEnum::String => $this->text($value),
            DataTypesEnum::Integer => preg_match('/^-?\d+$/', $value) === 1 ? $value : throw new InvalidPropertyValue('must be a whole number.'),
            DataTypesEnum::Float => is_numeric($value) ? $value : throw new InvalidPropertyValue('must be a number.'),
            DataTypesEnum::Boolean => match (strtolower($value)) {
                '1', 'true', 'yes', 'y' => '1',
                '0', 'false', 'no', 'n' => '0',
                default => throw new InvalidPropertyValue('must be yes or no.'),
            },
            DataTypesEnum::DateTime => $this->date($value, FileProperties::DATE_TIME_FORMAT),
            DataTypesEnum::Time => $this->date($value, FileProperties::TIME_FORMAT),
            DataTypesEnum::List => $this->option($definition, $value),
        };
    }

    /**
     * @throws InvalidPropertyValue
     */
    private function text(string $value): string
    {
        if (mb_strlen($value) > self::MAX_TEXT_LENGTH) {
            throw new InvalidPropertyValue('is too long (maximum ' . self::MAX_TEXT_LENGTH . ' characters).');
        }

        return $value;
    }

    /**
     * @throws InvalidPropertyValue
     */
    private function date(string $value, string $format): string
    {
        try {
            return Carbon::parse($value)->format($format);
        } catch (Throwable) {
            throw new InvalidPropertyValue('is not a valid date/time.');
        }
    }

    /**
     * @throws InvalidPropertyValue
     */
    private function option(EventProperty $definition, string $value): string
    {
        $allowed = collect($definition->Options ?? [])->pluck('value')->map(fn ($v) => (string) $v);

        return $allowed->contains($value)
            ? $value
            : throw new InvalidPropertyValue('must be one of: ' . $allowed->implode(', ') . '.');
    }
}
