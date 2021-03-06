<?php declare(strict_types=1);

namespace Symplify\BetterReflectionDocBlock\Tag;

use phpDocumentor\Reflection\DocBlock\Description;
use phpDocumentor\Reflection\DocBlock\DescriptionFactory;
use phpDocumentor\Reflection\DocBlock\Tags\BaseTag;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\TypeResolver;
use phpDocumentor\Reflection\Types\Context;
use Throwable;
use Webmozart\Assert\Assert;

/**
 * Same as @see \phpDocumentor\Reflection\DocBlock\Tags\Param,
 * just more tolerant to input. Allows invalid type in param tag, e.g.
 *
 * - "_param (Command|string)[] $commands"
 */
final class TolerantParam extends BaseTag
{
    /**
     * @var string
     */
    protected $name = 'param';

    /**
     * @var Type|null|false
     */
    private $type;

    /**
     * @var string
     */
    private $variableName = '';

    /**
     * @var bool determines whether this is a variadic argument
     */
    private $isVariadic = false;

    public function __construct(
        string $variableName,
        ?Type $type = null,
        bool $isVariadic = false,
        ?Description $description = null
    ) {
        $this->variableName = $variableName;
        $this->type = $type;
        $this->isVariadic = $isVariadic;
        $this->description = $description;
    }

    public function __toString(): string
    {
        return ($this->type ? $this->type . ' ' : '')
            . ($this->isVariadic() ? '...' : '')
            . '$' . $this->variableName
            . ($this->description ? ' ' . $this->description : '');
    }

    /**
     * {@inheritdoc}
     */
    public static function create(
        $body,
        ?TypeResolver $typeResolver = null,
        ?DescriptionFactory $descriptionFactory = null,
        ?Context $context = null
    ): self {
        Assert::stringNotEmpty($body);
        Assert::allNotNull([$typeResolver, $descriptionFactory]);

        $parts = preg_split('/(\s+)/Su', $body, 3, PREG_SPLIT_DELIM_CAPTURE);
        $type = null;
        $variableName = '';
        $isVariadic = false;

        // if the first item that is encountered is not a variable; it is a type
        if (isset($parts[0]) && (strlen($parts[0]) > 0) && ($parts[0][0] !== '$')) {
            try {
                $type = $typeResolver->resolve(array_shift($parts), $context);
            } catch (Throwable $throwable) {
                $type = null;
            }

            array_shift($parts);
        }

        // if the next item starts with a $ or ...$ it must be the variable name
        if (self::isVariadicParam($parts)) {
            $variableName = array_shift($parts);
            array_shift($parts);

            if (substr($variableName, 0, 3) === '...') {
                $isVariadic = true;
                $variableName = substr($variableName, 3);
            }

            if (substr($variableName, 0, 1) === '$') {
                $variableName = substr($variableName, 1);
            }
        }

        $description = $descriptionFactory->create(implode('', $parts), $context);

        return new static($variableName, $type, $isVariadic, $description);
    }

    public function getVariableName(): string
    {
        return $this->variableName;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function isVariadic(): bool
    {
        return $this->isVariadic;
    }

    /**
     * @param mixed[] $parts
     */
    private static function isVariadicParam(array $parts): bool
    {
        return isset($parts[0])
            && strlen($parts[0]) > 0
            && $parts[0][0] === '$' || substr($parts[0], 0, 4) === '...$';
    }
}
