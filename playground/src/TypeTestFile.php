<?php

declare(strict_types=1);

namespace Playground;

/**
 * Test file for verifying definition, type-definition, and member completion.
 */
class TypeTestFile
{
    public function testVariableTypes(): void
    {
        $calc = new Calculator();
        $result = $calc->add(5.0)->getResult();

        $user = new User('John', 'john@example.com');
        $name = $user->getName();

        $greeter = new Greeter('World');
        $greeting = $greeter->greet();

        $status = StatusEnum::Active;
    }

    public function testParameterTypes(Calculator $calc, User $user): User
    {
        $name = $user->getName();

        return $user;
    }

    public function testMethodChaining(): float
    {
        $calc = new Calculator();

        return $calc->add(1.0)->subtract(2.0)->multiply(3.0)->getResult();
    }
}
