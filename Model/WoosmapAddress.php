<?php

declare(strict_types=1);

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider\Woosmap\Model;

use Geocoder\Model\Address;
use Geocoder\Model\AdminLevel;
use Geocoder\Model\AdminLevelCollection;

final class WoosmapAddress extends Address
{
    /**
     * @var string|null
     */
    private $id;

    /**
     * @var string|null
     */
    private $locationType;

    /**
     * @var array
     */
    private $resultType = [];

    /**
     * @var string|null
     */
    private $formattedAddress;

    /**
     * @var string|null
     */
    private $county;

    /**
     * @var string|null
     */
    private $political;

    /**
     * @var string|null
     */
    private $state;

    /**
     * @var AdminLevelCollection
     */
    private $subLocalityLevels;

    /**
     * @param null|string $id
     *
     * @return WoosmapAddress
     */
    public function withId(string $id = null)
    {
        $new = clone $this;
        $new->id = $id;

        return $new;
    }

    /**
     * @return null|string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param null|string $locationType
     *
     * @return WoosmapAddress
     */
    public function withLocationType(string $locationType = null)
    {
        $new = clone $this;
        $new->locationType = $locationType;

        return $new;
    }

    /**
     * @return null|string
     */
    public function getLocationType()
    {
        return $this->locationType;
    }

    /**
     * @return array
     */
    public function getResultType(): array
    {
        return $this->resultType;
    }

    /**
     * @param array $resultType
     *
     * @return WoosmapAddress
     */
    public function withResultType(array $resultType)
    {
        $new = clone $this;
        $new->resultType = $resultType;

        return $new;
    }

    /**
     * @return null|string
     */
    public function getFormattedAddress()
    {
        return $this->formattedAddress;
    }

    /**
     * @param string|null $formattedAddress
     *
     * @return WoosmapAddress
     */
    public function withFormattedAddress(string $formattedAddress = null)
    {
        $new = clone $this;
        $new->formattedAddress = $formattedAddress;

        return $new;
    }

    /**
     * @return null|string
     */
    public function getCounty()
    {
        return $this->county;
    }

    /**
     * @param string|null $county
     *
     * @return WoosmapAddress
     */
    public function withCounty(string $county = null)
    {
        $new = clone $this;
        $new->county = $county;

        return $new;
    }

    /**
     * @return null|string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param string|null $state
     *
     * @return WoosmapAddress
     */
    public function withState(string $state = null)
    {
        $new = clone $this;
        $new->state = $state;

        return $new;
    }

    /**
     * @return null|string
     */
    public function getPolitical()
    {
        return $this->political;
    }

    /**
     * @param string|null $political
     *
     * @return WoosmapAddress
     */
    public function withPolitical(string $political = null)
    {
        $new = clone $this;
        $new->political = $political;

        return $new;
    }

    /**
     * @return AdminLevelCollection
     */
    public function getSubLocalityLevels()
    {
        return $this->subLocalityLevels;
    }

    /**
     * @param array $subLocalityLevel
     *
     * @return $this
     */
    public function withSubLocalityLevels(array $subLocalityLevel)
    {
        $subLocalityLevels = [];
        foreach ($subLocalityLevel as $level) {
            if (empty($level['level'])) {
                continue;
            }

            $name = $level['name'] ?? $level['code'] ?? '';
            if (empty($name)) {
                continue;
            }

            $subLocalityLevels[] = new AdminLevel($level['level'], $name, $level['code'] ?? null);
        }

        $subLocalityLevels = array_unique($subLocalityLevels);

        $new = clone $this;
        $new->subLocalityLevels = new AdminLevelCollection($subLocalityLevels);

        return $new;
    }

    /**
     * @return bool
     */
    public function isPartialMatch()
    {
        return $this->partialMatch;
    }

    /**
     * @param bool $partialMatch
     *
     * @return $this
     */
    public function withPartialMatch(bool $partialMatch)
    {
        $new = clone $this;
        $new->partialMatch = $partialMatch;

        return $new;
    }
}
