<?php namespace Cms\Traits;

/**
 * ParsableAttributes allows CMS templates to use dynamic attributes
 *
 *     meta_title = "Blog - {{ post.title }}"
 *
 */
trait ParsableAttributes
{
    /**
     * @var array parsable attributes support using twig code.
     *
     * public $parsable = [];
     */

    /**
     * @var array parsableAttributes contains the translated attributes
     */
    protected $parsableAttributes = [];

    /**
     * offsetGet will leverage Twig's workflow where array access is first in line.
     */
    public function offsetGet($offset): mixed
    {
        $value = parent::offsetGet($offset);

        if (
            in_array($offset, $this->parsable) &&
            isset($this->parsableAttributes[$offset])
        ) {
            return $this->parsableAttributes[$offset];
        }

        return $value;
    }

    /**
     * addParsable attributes for the model
     */
    public function addParsable(...$attributes)
    {
        if (is_array($attributes[0])) {
            $attributes = $attributes[0];
        }

        $this->parsable = array_merge($this->parsable, $attributes);
    }

    /**
     * setParsableAttribute
     */
    public function setParsableAttribute(string $key, $value): void
    {
        $this->parsableAttributes[$key] = $value;
    }

    /**
     * getParsableAttributes
     */
    public function getParsableAttributeValues(): array
    {
        $values = [];

        foreach ($this->parsable as $key) {
            $values[$key] = $this->$key;
        }

        return $values;
    }
}
