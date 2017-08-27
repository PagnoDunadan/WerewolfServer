<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * @ORM\Entity
 * @ORM\Table(name="room")
 */
class Room
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="string")
     */
    private $owner;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="string")
     */
    private $gameStatus;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="string")
     */
    private $gamePhase;

    public function getId()
    {
        return $this->id;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function setOwner($owner)
    {
        $this->owner = $owner;
    }

    public function getGameStatus()
    {
        return $this->gameStatus;
    }

    public function setGameStatus($gameStatus)
    {
        $this->gameStatus = $gameStatus;
    }

    public function getGamePhase()
    {
        return $this->gamePhase;
    }

    public function setGamePhase($gamePhase)
    {
        $this->gamePhase = $gamePhase;
    }

}