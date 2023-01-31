<?php
/**
 * Wayfinder data object.
 *
 * @author Inlead
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link https://inlead.dk
 */

namespace Finna\Wayfinder\DTO;

/**
 * Wayfinder data object class.
 *
 * @author Inlead
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link https://inlead.dk
 */
class WayfinderMarker
{
    /**
     * @var string Branch.
     */
    protected string $branch;

    /**
     * @var string|null Department.
     */
    protected ?string $department = null;

    /**
     * @var string|null Location.
     */
    protected ?string $location = null;

    /**
     * @var string|null Sub-location.
     */
    protected ?string $subLocation = null;

    /**
     * @var string|null Shelf-mark.
     */
    protected ?string $shelfMark = null;

    /**
     * @var string|null DK5.
     */
    protected ?string $dk5 = null;

    /**
     * @var string|null Author.
     */
    protected ?string $author = null;

    /**
     * @var string|null Dewey.
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
     * @param string $branch
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
     * @param string|null $department
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
     * @param string|null $location
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
     * @param string|null $subLocation
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
     * Sets shelfmark placement.
     *
     * @param string|null $shelfMark
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
     * @param string|null $dk5
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
     * @param string|null $author
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
     * @param string|null $dewey
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
