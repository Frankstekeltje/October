<?php namespace Cms\Traits;

use Cms\Classes\CmsObject;
use Cms\Classes\ComponentBase;

/**
 * ParsableController adds property and attribute parsing logic to the CMS controller
 */
trait ParsableController
{
    /**
     * parseAllEnvironmentVars parses vars for all relevant objects.
     */
    protected function parseAllEnvironmentVars()
    {
        $this->parseEnvironmentVarsOnTemplate($this->page, $this->vars);
        $this->parseEnvironmentVarsOnTemplate($this->layout, $this->vars);

        foreach ($this->layout->components as $component) {
            $this->parseEnvironmentVarsOnComponent($component, $this->vars);
        }

        foreach ($this->page->components as $component) {
            $this->parseEnvironmentVarsOnComponent($component, $this->vars);
        }
    }

    /**
     * parseRouteParamsOnComponent where property values should be defined as {{ :param }}.
     */
    protected function parseRouteParamsOnComponent(ComponentBase $component)
    {
        $properties = $component->getProperties();

        // Apply external route parameters
        $routerParameters = $this->router->getParameters();
        $overrides = $this->makeRouterPropertyReplacements($properties, $routerParameters);

        foreach ($overrides as $propertyName => $override) {
            $component->setProperty($propertyName, $override[1]);
            $component->setExternalPropertyName($propertyName, $override[0]);
        }
    }

    /**
     * parseEnvironmentVarsOnComponent where property values should be defined as {{ param }}.
     */
    protected function parseEnvironmentVarsOnComponent(ComponentBase $component, array $vars = [])
    {
        $properties = $component->getProperties();

        // Apply environment variables
        $overrides = $this->makeDynamicAttributeReplacements($properties, $vars);

        foreach ($overrides as $propertyName => $override) {
            $component->setProperty($propertyName, $override[1]);
            $component->setExternalPropertyName($propertyName, $override[0]);
        }
    }

    /**
     * parseEnvironmentVarsOnTemplate where property values should be defined as {{ param }}.
     */
    protected function parseEnvironmentVarsOnTemplate(CmsObject $template)
    {
        $attributes = $template->getParsableAttributeValues();

        // Apply environment variables
        $overrides = $this->makeDynamicAttributeReplacements($attributes, $this->vars);

        foreach ($overrides as $attrName => $override) {
            $template->setParsableAttribute($attrName, $override[1]);
        }
    }

    /**
     * makeRouterPropertyReplacements will look inside property values to replace any
     * Twig-like variables with values from the route parameters.
     *
     *     {{ :post }}
     */
    protected function makeRouterPropertyReplacements($properties, array $routerParameters = [])
    {
        $result = [];

        foreach ($properties as $propertyName => $propertyValue) {
            if (is_array($propertyValue)) {
                continue;
            }

            $matches = [];
            if (preg_match('/^\{\{(\s*:[^\}]+)\}\}$/', $propertyValue, $matches)) {
                $paramName = trim($matches[1]);
                $routeParamName = substr($paramName, 1);
                $newPropertyValue = $routerParameters[$routeParamName] ?? null;
                $result[$propertyName] = [$paramName, $newPropertyValue];
            }
        }

        return $result;
    }

    /**
     * makeDynamicAttributeReplacements will look inside attribute values to replace any
     * Twig-like variables with the values inside the parameters.
     *
     *     {{ post.title }}
     */
    protected function makeDynamicAttributeReplacements($attributes, array $parameters = [])
    {
        $result = [];

        foreach ($attributes as $attrName => $attrValue) {
            if (is_array($attrValue)) {
                continue;
            }

            $matches = [];
            if (preg_match_all('/\{\{([^:\}]+)\}\}/', $attrValue, $matches)) {
                $newAttrValue = $attrValue;
                $lastParamName = null;

                foreach ($matches[1] as $key => $paramName) {
                    $paramName = $lastParamName = trim($paramName);
                    $replaceWith = array_get($parameters, $paramName);
                    $toReplace = $matches[0][$key];
                    $newAttrValue = str_replace($toReplace, $replaceWith, $newAttrValue);
                }

                $result[$attrName] = [$lastParamName, $newAttrValue];
            }
        }

        return $result;
    }
}
