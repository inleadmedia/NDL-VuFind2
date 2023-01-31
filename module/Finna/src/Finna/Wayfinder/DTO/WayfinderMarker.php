<?php

namespace Finna\Wayfinder\DTO;

class WayfinderMarker
{
    protected string $branch;

    protected ?string $department = null;

    protected ?string $location = null;

    protected ?string $subLocation = null;

    protected ?string $shelfMark = null;

    protected ?string $dk5 = null;

    protected ?string $author = null;

    protected ?string $dewey = null;

    /**
     * @return string
     */
    public function getBranch(): string
    {
        return $this->branch;
    }

    /**
     * @param string $branch
     * @return WayfinderMarker
     */
    public function setBranch(string $branch): WayfinderMarker
    {
        $this->branch = $branch;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDepartment(): ?string
    {
        return $this->department;
    }

    /**
     * @param string|null $department
     * @return WayfinderMarker
     */
    public function setDepartment(?string $department): WayfinderMarker
    {
        $this->department = $department;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * @param string|null $location
     * @return WayfinderMarker
     */
    public function setLocation(?string $location): WayfinderMarker
    {
        $this->location = $location;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSubLocation(): ?string
    {
        return $this->subLocation;
    }

    /**
     * @param string|null $subLocation
     * @return WayfinderMarker
     */
    public function setSubLocation(?string $subLocation): WayfinderMarker
    {
        $this->subLocation = $subLocation;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getShelfMark(): ?string
    {
        return $this->shelfMark;
    }

    /**
     * @param string|null $shelfMark
     * @return WayfinderMarker
     */
    public function setShelfMark(?string $shelfMark): WayfinderMarker
    {
        $this->shelfMark = $shelfMark;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDk5(): ?string
    {
        return $this->dk5;
    }

    /**
     * @param string|null $dk5
     * @return WayfinderMarker
     */
    public function setDk5(?string $dk5): WayfinderMarker
    {
        $this->dk5 = $dk5;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAuthor(): ?string
    {
        return $this->author;
    }

    /**
     * @param string|null $author
     * @return WayfinderMarker
     */
    public function setAuthor(?string $author): WayfinderMarker
    {
        $this->author = $author;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDewey(): ?string
    {
        return $this->dewey;
    }

    /**
     * @param string|null $dewey
     * @return WayfinderMarker
     */
    public function setDewey(?string $dewey): WayfinderMarker
    {
        $this->dewey = $dewey;
        return $this;
    }

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
