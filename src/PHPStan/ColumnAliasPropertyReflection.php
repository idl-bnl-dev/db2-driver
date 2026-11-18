<?php

namespace IDL\DB2Driver\PHPStan;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Type;

/** A readable and writable virtual property backed by a real column. */
final class ColumnAliasPropertyReflection implements PropertyReflection
{
    public function __construct(private ClassReflection $declaringClass, private Type $type) {}

    public function getDeclaringClass(): ClassReflection
    {
        return $this->declaringClass;
    }

    public function isStatic(): bool
    {
        return false;
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function getDocComment(): ?string
    {
        return null;
    }

    public function getReadableType(): Type
    {
        return $this->type;
    }

    public function getWritableType(): Type
    {
        return $this->type;
    }

    public function canChangeTypeAfterAssignment(): bool
    {
        return false;
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function isDeprecated(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getDeprecatedDescription(): ?string
    {
        return null;
    }

    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }
}
