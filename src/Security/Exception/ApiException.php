<?php

namespace App\Security\Exception;

use Throwable;

class ApiException extends \Exception
{
    public function __construct(
        string $message = "",
        int $code = 0,
        Throwable|null $previous = null,
        private readonly array $context = []
    )
    {
        parent::__construct($message, $code, $previous);
    }

    public function getErrors(): array
    {
        return $this->context;
    }

    // === Violations de validation === //

    public function getViolations(): array
    {
        return $this->context['violations'] ?? [];
    }

    /**
     * Permet d'indiquer s'il y a des violations de validation '422'
     * @return bool
     */
    public function hasViolations(): bool
    {
        return !empty($this->context['violations']);
    }

    /**
     * Permet de retourner le premier message de violation pour un champ donné ou null
     * @param string $field
     */
    public function getViolationFor(string $field): ?string
    {
        return ($this->context['violations'][$field] ?? [])[0] ?? null;
    }

    /**
     * Permet d'aplatir toutes les violations en liste de strings lisibles
     * @return array
     */
    public function getFlatViolations(): array
    {
        $result = [];
        foreach($this->context['violations'] ?? [] as $field => $messages) {
            foreach ($messages as $msg) {
                $result[] = $field !== 'global' ? "{$field} : {$msg}" : $msg;
            }
        }
        return $result;
    }

    // === Helper === //

    public function getContext(): array
    {
        return $this->context;
    }

    public function getRaw(): array
    {
        return $this->context['raw'] ?? [];
    }

    public function isNotFound(): bool
    {
        return $this->getCode() === 404;
    }

    public function isValidationError(): bool
    {
        return $this->getCode() === 422;
    }

    public function isServerError(): bool
    {
        return $this->getCode() >= 500;
    }

    public function isForbidden(): bool
    {
        return $this->getCode() === 403;
    }
}