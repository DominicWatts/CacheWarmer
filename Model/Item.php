<?php

declare(strict_types=1);

namespace Xigen\CacheWarmer\Model;

class Item implements ItemInterface
{
    /**
     * @var string
     */
    private $url;

    /**
     * Item constructor.
     * @param string $url
     */
    public function __construct($url)
    {
        $this->url = $url;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl()
    {
        return $this->url;
    }
}
