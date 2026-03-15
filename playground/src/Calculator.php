<?php

declare(strict_types=1);

namespace Playground;

/**
 * Calculator class for testing completion, signatures, and references.
 */
class Calculator
{
    private float $result = 0.0;

    /**
     * Add a value to the current result.
     *
     * @param float $value The value to add
     * @return self Returns self for chaining
     */
    public function add(float $value): self
    {
        $this->result += $value;
        return $this;
    }

    /**
     * Subtract a value from the current result.
     *
     * @param float $value The value to subtract
     * @return self Returns self for chaining
     */
    public function subtract(float $value): self
    {
        $this->result -= $value;
        return $this;
    }

    /**
     * Multiply the current result by a value.
     *
     * @param float $value The multiplier
     * @return self Returns self for chaining
     */
    public function multiply(float $value): self
    {
        $this->result *= $value;
        return $this;
    }

    /**
     * Divide the current result by a value.
     *
     * @param float $divisor The divisor (must not be zero)
     * @throws \DivisionByZeroError When divisor is zero
     * @return self Returns self for chaining
     */
    public function divide(float $divisor): self
    {
        if ($divisor === 0.0) {
            throw new \DivisionByZeroError('Division by zero');
        }
        $this->result /= $divisor;
        return $this;
    }

    /**
     * Get the current result.
     */
    public function getResult(): float
    {
        return $this->result;
    }

    /**
     * Reset the calculator to zero.
     */
    public function reset(): self
    {
        $this->result = 0.0;
        return $this;
    }
}
