<?php
/**
 * Wayfinder data object.
 *
 * PHP version 7
 *
 * @category Wayfinder
 * @package  Wayfinder
 * @author   Inlead <support@inlead.dk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://inlead.dk
 */
namespace Finna\Wayfinder\DTO;

/**
 * Wayfinder data object class.
 *
 * @category Wayfinder
 * @package  Wayfinder
 * @author   Inlead <support@inlead.dk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://inlead.dk
 */
class WayfinderMarker
{
    /**
     * Branch placement.
     *
     * @var string
     */
    protected string $branch;

    /**
     * Department placement.
     *
     * @var string|null
     */
    protected ?string $department = null;

    /**
     * Location placement.
     *
     * @var string|null
     */
    protected ?string $location = null;

    /**
     * Sub-location placement.
     *
     * @var string|null
     */
    protected ?string $subLocation = null;

    /**
     * Shelf-mark placement.
     *
     * @var string|null
     */
    protected ?string $shelfMark = null;

    /**
     * DK5 placement.
     *
     * @var string|null
     */
    protected ?string $dk5 = null;

    /**
     * Author placement.
     *
     * @var string|null
     */
    protected ?string $author = null;

    /**
     * Dewey placement.
     *
     * @var string|null
     */
    protected ?string $dewey = null;

    /**
     * Gets branch placement.
     *
     * @return string
     */
    public function getBranch(): string
    {
        return $this->branch;
    }

    /**
     * Sets branch placement.
     *
     * @param string $branch Branch placement.
     *
     * @return WayfinderMarker
     */
    public function setBranch(string $branch): WayfinderMarker
    {
        $this->branch = $branch;
        return $this;
    }

    /**
     * Gets department placement.
     *
     * @return string|null
     */
    public function getDepartment(): ?string
    {
        return $this->department;
    }

    /**
     * Sets department placement.
     *
     * @param string|null $department Department placement.
     *
     * @return WayfinderMarker
     */
    public function setDepartment(?string $department): WayfinderMarker
    {
        $this->department = $department;
        return $this;
    }

    /**
     * Gets location placement.
     *
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * Sets location placement.
     *
     * @param string|null $location Location placement.
     *
     * @return WayfinderMarker
     */
    public function setLocation(?string $location): WayfinderMarker
    {
        $this->location = $location;
        return $this;
    }

    /**
     * Sets sub-location placement.
     *
     * @return string|null
     */
    public function getSubLocation(): ?string
    {
        return $this->subLocation;
    }

    /**
     * Gets sub-location placement.
     *
     * @param string|null $subLocation Sub-location placement.
     *
     * @return WayfinderMarker
     */
    public function setSubLocation(?string $subLocation): WayfinderMarker
    {
        $this->subLocation = $subLocation;
        return $this;
    }

    /**
     * Gets shelfmark placement.
     *
     * @return string|null
     */
    public function getShelfMark(): ?string
    {
        return $this->shelfMark;
    }

    /**
     * Sets shelf-mark placement.
     *
     * @param string|null $shelfMark Shelf-mark placement.
     *
     * @return WayfinderMarker
     */
    public function setShelfMark(?string $shelfMark): WayfinderMarker
    {
        $this->shelfMark = $shelfMark;
        return $this;
    }

    /**
     * Gets dk5 placement.
     *
     * @return string|null
     */
    public function getDk5(): ?string
    {
        return $this->dk5;
    }

    /**
     * Sets dk5 placement.
     *
     * @param string|null $dk5 DK5 placement.
     *
     * @return WayfinderMarker
     */
    public function setDk5(?string $dk5): WayfinderMarker
    {
        $this->dk5 = $dk5;
        return $this;
    }

    /**
     * Gets author placement.
     *
     * @return string|null
     */
    public function getAuthor(): ?string
    {
        return $this->author;
    }

    /**
     * Sets author placement.
     *
     * @param string|null $author Author placement.
     *
     * @return WayfinderMarker
     */
    public function setAuthor(?string $author): WayfinderMarker
    {
        $this->author = $author;
        return $this;
    }

    /**
     * Gets dewey placement.
     *
     * @return string|null
     */
    public function getDewey(): ?string
    {
        return $this->dewey;
    }

    /**
     * Sets dewey placement.
     *
     * @param string|null $dewey Dewey value.
     *
     * @return WayfinderMarker
     */
    public function setDewey(?string $dewey): WayfinderMarker
    {
        $this->dewey = $dewey;
        return $this;
    }

    /**
     * Gets dto array representation.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'branch' => $this->branch,
            'department' => $this->department,
            'location' => $this->location,
            'sublocation' => $this->subLocation,
            'shelfmark' => $this->shelfMark,
            'dk5' => $this->dk5,
            'author' => $this->author,
            'dewey' => $this->dewey,
        ];
    }
}
