<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Game;
use AppBundle\Entity\Room;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR,
        ]);
    }

    /**
     * @Route("/create-game", name="create-game")
     * @Method("POST")
     */
    public function createGameAction(Request $request)
    {

        $em = $this->getDoctrine()->getManager();

        $room = new Room();
        $room->setOwner($request->request->get('playerName'));
        $room->setGameStatus('new');

        $em->persist($room);
        $em->flush();

        $id = $room->getId();

        $game = new Game();
        $game->setRoomId($id);
        $game->setPlayerName($request->request->get('playerName'));
        $game->setRole('Unspecified');

        $em->persist($game);
        $em->flush();

        return new Response($id);

    }

//    /**
//     * @Route("/delete-game", name="delete-game")
//     * @Method("POST")
//     */
//    public function deleteGameAction(Request $request)
//    {
//
//        $em = $this->getDoctrine()->getManager();
//
//        $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
//        $games = $em->getRepository(Game::class)->findBy(array('roomId' => $request->request->get('roomId') ));
//
//        if (!$room) {
//            return new Response('RoomNotFound');
//        }
//        if (!$games) {
//            return new Response('GameNotFound');
//        }
//
//        $em->remove($room);
//        foreach ($games as $game) {
//            $em->remove($game);
//        }
//        $em->flush();
//
//        return new Response('DeleteGameSuccessful');
//
//    }

    /**
     * @Route("/remove-player", name="remove-player")
     * @Method("POST")
     */
    public function removePlayerAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $player = $em->getRepository(Game::class)->findBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $request->request->get('playerName')
        ));

        // Player not found in the room
        if (!$player) {
            return new Response('PlayerNotFound');
        }

        // Remove player from the room
        foreach ($player as $p) {
            $em->remove($p);
        }
        $em->flush();

        $players = $em->getRepository(Game::class)->findBy(array(
            'roomId' => $request->request->get('roomId')
        ));

        // If there are no players in the room, delete it
        if (!$players) {
            $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
            if($room) {
                $em->remove($room);
                $em->flush();
            }
            return new Response('NoMorePlayers');
        }
        // If room doesn't exist but there are players, remove players
        else if ($players) {
            $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
            if(!$room) {
                foreach ($players as $player) {
                    $em->remove($player);
                }
                $em->flush();
                return new Response('RoomNotFound');
            }
        }

        return new Response('RemovePlayerSuccessful');
    }

    /**
     * @Route("/join-game", name="join-game")
     * @Method("POST")
     */
    public function joinGameAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
        $players = $em->getRepository(Game::class)->findBy(array('roomId' => $request->request->get('roomId') ));

        // If room doesn't exist but there are players, remove players
        if (!$room) {
            if($players) {
                foreach ($players as $player) {
                    $em->remove($player);
                }
                $em->flush();
            }
            return new Response('RoomNotFound');
        }

        // If there are no players in the room, delete it
        if (!$players) {
            if($room) {
                $em->remove($room);
                $em->flush();
            }
            return new Response('PlayersNotFound');
        }

        // If game is new, and name is not in use, join
        if ($room->getGameStatus() == 'new') {
            $names = $em->getRepository(Game::class)->findBy(array(
                'roomId' => $request->request->get('roomId'),
                'playerName' => $request->request->get('playerName')
            ));

            // If name is already in use
            if ($names) {
                return new Response('NameInUse');
            }

            // Join game
            $game = new Game();
            $game->setRoomId($request->request->get('roomId'));
            $game->setPlayerName($request->request->get('playerName'));
            $game->setRole('Unspecified');

            $em->persist($game);
            $em->flush();
            return new Response('JoinGameSuccessful');
        }

        // If game is in progress, and player is in the game, reconnect
        else if ($room->getGameStatus() == 'inProgress') {
            $players = $em->getRepository(Game::class)->findBy(array(
                'roomId' => $request->request->get('roomId'),
                'playerName' => $request->request->get('playerName')
            ));

            // If player is in the game, reconnect
            if ($players) {
                return new Response('ReconnectSuccessful');
            }
            // If player is NOT in the game, refuse connection
            else {
                return new Response('MatchInProgress');
            }
        }

        // If game is finished
        else {
            return new Response('GameFinished');
        }
    }

    /**
     * @Route("/players-list", name="players-list")
     * @Method("POST")
     */
    public function playersListAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $players = $em->getRepository(Game::class)->findBy(array('roomId' => $request->request->get('roomId') ));

        // If there are no players, delete room
        if (!$players) {
            $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
            if($room) {
                $em->remove($room);
                $em->flush();
            }
            return new Response('PlayersNotFound');
        }

        $encoders = array(new XmlEncoder(), new JsonEncoder());
        $normalizers = array(new ObjectNormalizer());

        $serializer = new Serializer($normalizers, $encoders);

        $jsonContent = $serializer->serialize($players, 'json');

        return new Response($jsonContent);
    }

    /**
     * @Route("/start-game", name="start-game")
     * @Method("POST")
     */
    public function startGameAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
        $players = $em->getRepository(Game::class)->findBy(array('roomId' => $request->request->get('roomId') ));

        // If room doesn't exist, remove players
        if(!$room) {
            if($players) {
                foreach ($players as $player) {
                    $em->remove($player);
                }
                $em->flush();
            }
            return new Response('RoomNotFound');
        }

        // If there are no players, delete room
        if (!$players) {
            if($room) {
                $em->remove($room);
                $em->flush();
            }
            return new Response('PlayersNotFound');
        }

        // Minimum number of players = 7, maximum = 16
        $numberOfPlayers = count($players);
        if ($numberOfPlayers < 7) {
            return new Response('MinimumPlayers7');
        }
        else if ($numberOfPlayers > 16) {
            return new Response('MaximumPlayers16');
        }

        // Generate 4 random players and assign them roles
        $randomPlayers = array();

        for ($i = 1; $i <= 4; $i++) {
            $randomNumber = rand(0, $numberOfPlayers - 1);

            // If this number was already generated, repeat
            if (in_array($randomNumber, $randomPlayers)) {
                $i--;
            }
            else {
                $randomPlayers[] = $randomNumber;
                if ($i == 1) {
                    $players[$randomNumber]->setRole('Seer');
                }
                else if ($i == 2) {
                    $players[$randomNumber]->setRole('Doctor');
                }
                else if ($i == 3) {
                    $players[$randomNumber]->setRole('Werewolf');
                }
                else if ($i == 4) {
                    $players[$randomNumber]->setRole('Werewolf');
                }
            }
        }
        $em->flush();

        // Assign roles to all other players
        $players = $em->getRepository(Game::class)->findBy(array('roomId' => $request->request->get('roomId') ));

        foreach ($players as $player) {
            if ($player->getRole() == 'Unspecified') {
                $player->setRole('Villager');
            }
        }
        $em->flush();

        $room->setGameStatus('inProgress');
        $em->flush();

        return new Response('GameStarted');
    }

    /**
     * @Route("/{genusName}")
     */
    public function showAction($genusName)
    {

        return new Response('The genus: '.$genusName);
    }

    /**
     * @Route("/genus2/{genusName}")
     */
    public function showAction2($genusName)
    {
        $notes = [
            'Octopus asked me a riddle, outsmarted me',
            'I counted 8 legs... as the wrapped around me',
            'Inked!'
        ];

        return $this->render('genus/show2.html.twig', [
            'name' => $genusName,
            'notes' => $notes
        ]);

    }

    /**
     * @Route("/genus3/{genusName}")
     */
    public function showAction3($genusName)
    {

        return $this->render('genus/show3.html.twig', [
            'name' => $genusName,
        ]);

    }

    /**
     * @Route("/genus/{genusName}/notes", name="genus_show_notes")
     * @Method("GET")
     */
    public function getNotesAction()
    {
        $notes = [
            ['id' => 1, 'username' => 'AquaPelham', 'avatarUri' => '/images/leanna.jpeg', 'note' => 'Octopus asked me a riddle, outsmarted me', 'date' => 'Dec. 10, 2015'],
            ['id' => 2, 'username' => 'AquaWeaver', 'avatarUri' => '/images/ryan.jpeg', 'note' => 'I counted 8 legs... as they wrapped around me', 'date' => 'Dec. 1, 2015'],
            ['id' => 3, 'username' => 'AquaPelham', 'avatarUri' => '/images/leanna.jpeg', 'note' => 'Inked!', 'date' => 'Aug. 20, 2015'],
        ];

        $data = [
            'notes' => $notes,
        ];

        return new JsonResponse($data);



    }
}
