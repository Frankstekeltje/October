<?php namespace Cms\Twig;

use Block;
use Event;
use Twig\Extension\AbstractExtension as TwigExtension;
use Twig\TwigFilter as TwigSimpleFilter;
use Twig\TwigFunction as TwigSimpleFunction;
use Cms\Classes\Controller;

/**
 * Extension implements the basic CMS Twig functions and filters.
 *
 * @package october\cms
 * @author Alexey Bobkov, Samuel Georges
 */
class Extension extends TwigExtension
{
    /**
     * @var \Cms\Classes\Controller controller reference
     */
    protected $controller;

    /**
     * __construct the extension instance.
     */
    public function __construct(Controller $controller = null)
    {
        $this->controller = $controller;
    }

    /**
     * getFunctions returns a list of functions to add to the existing list.
     * @return array
     */
    public function getFunctions()
    {
        return [
            new TwigSimpleFunction('page', [$this, 'pageFunction'], ['is_safe' => ['html']]),
            new TwigSimpleFunction('partial', [$this, 'partialFunction'], ['is_safe' => ['html']]),
            new TwigSimpleFunction('content', [$this, 'contentFunction'], ['is_safe' => ['html']]),
            new TwigSimpleFunction('component', [$this, 'componentFunction'], ['is_safe' => ['html']]),
            new TwigSimpleFunction('placeholder', [$this, 'placeholderFunction'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * getFilters returns a list of filters this extensions provides.
     * @return array
     */
    public function getFilters()
    {
        return [
            new TwigSimpleFilter('page', [$this, 'pageFilter'], ['is_safe' => ['html']]),
            new TwigSimpleFilter('theme', [$this, 'themeFilter'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * getTokenParsers returns a list of token parsers this extensions provides.
     * @return array
     */
    public function getTokenParsers()
    {
        return [
            new PageTokenParser,
            new PartialTokenParser,
            new ContentTokenParser,
            new PutTokenParser,
            new PlaceholderTokenParser,
            new DefaultTokenParser,
            new FrameworkTokenParser,
            new ComponentTokenParser,
            new FlashTokenParser,
            new ScriptsTokenParser,
            new StylesTokenParser,
        ];
    }

    /**
     * pageFunction renders a page.
     * This function should be used in the layout code to output the requested page.
     * @return string
     */
    public function pageFunction()
    {
        return $this->controller->renderPage();
    }

    /**
     * partialFunction renders a partial.
     * @param string $name Specifies the partial name.
     * @param array $parameters A optional list of parameters to pass to the partial.
     * @param bool $throwException Throw an exception if the partial is not found.
     * @return string
     */
    public function partialFunction($name, $parameters = [], $throwException = false)
    {
        return $this->controller->renderPartial($name, $parameters, $throwException);
    }

    /**
     * contentFunction renders a content file.
     * @param string $name Specifies the content block name.
     * @param array $parameters A optional list of parameters to pass to the content.
     * @return string
     */
    public function contentFunction($name, $parameters = [], $throwException = false)
    {
        return $this->controller->renderContent($name, $parameters, $throwException);
    }

    /**
     * componentFunction renders a component's default content.
     * @param string $name Specifies the component name.
     * @param array $parameters A optional list of parameters to pass to the component.
     * @return string
     */
    public function componentFunction($name, $parameters = [])
    {
        return $this->controller->renderComponent($name, $parameters);
    }

    /**
     * assetsFunction renders registered assets of a given type
     * @return string
     */
    public function assetsFunction($type = null)
    {
        $result = $this->controller->makeAssets($type);

        Event::fire('cms.assets.render', [$type, &$result]);

        return $result;
    }

    /**
     * placeholderFunction renders a placeholder content, without removing the block,
     * must be called before the placeholder tag itself
     * @return string
     */
    public function placeholderFunction($name, $default = null)
    {
        if (($result = Block::get($name)) === null) {
            return null;
        }

        $result = str_replace('<!-- X_OCTOBER_DEFAULT_BLOCK_CONTENT -->', trim($default), $result);

        return $result;
    }

    /**
     * pageFilter looks up the URL for a supplied page and returns it relative to the website root.
     * @param mixed $name Specifies the Cms Page file name.
     * @param array $parameters Route parameters to consider in the URL.
     * @param bool $routePersistence By default the existing routing parameters will be included
     * when creating the URL, set to false to disable this feature.
     * @return string
     */
    public function pageFilter($name, $parameters = [], $routePersistence = true)
    {
        return $this->controller->pageUrl($name, $parameters, $routePersistence);
    }

    /**
     * themeFilter converts supplied URL to a theme URL relative to the website root. If the URL provided is an
     * array then the files will be combined.
     * @param mixed $url Specifies the theme-relative URL
     * @return string
     */
    public function themeFilter($url)
    {
        return $this->controller->themeUrl($url);
    }

    /**
     * startBlock opens a layout block.
     * @param string $name Specifies the block name
     */
    public function startBlock($name)
    {
        Block::startBlock($name);
    }

    /**
     * setBlock sets a block value as a variable.
     */
    public function setBlock(string $name, $value)
    {
        Block::set($name, $value);
    }

    /**
     * displayBlock returns a layout block contents and removes the block.
     * @param string $name Specifies the block name
     * @param string $default The default placeholder contents.
     * @return string|null
     */
    public function displayBlock($name, $default = null)
    {
        if (($result = Block::placeholder($name)) === null) {
            return $default;
        }

        /**
         * @event cms.block.render
         * Provides an opportunity to modify the rendered block content
         *
         * Example usage:
         *
         *     Event::listen('cms.block.render', function ((string) $name, (string) $result) {
         *         if ($name === 'myBlockName') {
         *             return 'my custom content';
         *         }
         *     });
         *
         */
        if ($event = Event::fire('cms.block.render', [$name, $result], true)) {
            $result = $event;
        }

        $result = str_replace('<!-- X_OCTOBER_DEFAULT_BLOCK_CONTENT -->', trim($default), $result);

        return $result;
    }

    /**
     * endBlock closes a layout block.
     */
    public function endBlock($append = true)
    {
        Block::endBlock($append);
    }
}
