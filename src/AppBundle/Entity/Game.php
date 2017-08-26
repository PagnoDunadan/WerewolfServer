<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 * @ORM\Table(name="game")
 */
class Game
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $roomId;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="string")
     */
    private $playerName;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="string")
     */
    private $role;


    public function getRoomId()
    {
        return $this->roomId;
    }

    public function setRoomId($roomId)
    {
        $this->roomId = $roomId;
    }

    public function getPlayerName()
    {
        return $this->playerName;
    }

    public function setPlayerName($playerName)
    {
        $this->playerName = $playerName;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function setRole($role)
    {
        $this->role = $role;
    }

}