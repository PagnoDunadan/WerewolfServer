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
//    /**
//     * @Route("/", name="homepage")
//     */
//    public function indexAction(Request $request)
//    {
//        // replace this example code with whatever you need
//        return $this->render('default/index.html.twig', [
//            'base_dir' => realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR,
//        ]);
//    }

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
        $room->setGamePhase('none');

        $em->persist($room);
        $em->flush();

        $id = $room->getId();

        $game = new Game();
        $game->setRoomId($id);
        $game->setPlayerName($request->request->get('playerName'));
        $game->setRole('unspecified');
        $game->setPlayerStatus('alive');
        $game->setAction('');

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
            $name = $em->getRepository(Game::class)->findBy(array(
                'roomId' => $request->request->get('roomId'),
                'playerName' => $request->request->get('playerName')
            ));

            // If name is already in use
            if ($name) {
                return new Response('NameInUse');
            }

            // Join game
            $game = new Game();
            $game->setRoomId($request->request->get('roomId'));
            $game->setPlayerName($request->request->get('playerName'));
            $game->setRole('unspecified');
            $game->setPlayerStatus('alive');
            $game->setAction('');

            $em->persist($game);
            $em->flush();
            return new Response('JoinGameSuccessful');
        }

        // If game is in progress, and player is in the game, reconnect
        else if ($room->getGameStatus() == 'inProgress') {
            $player = $em->getRepository(Game::class)->findBy(array(
                'roomId' => $request->request->get('roomId'),
                'playerName' => $request->request->get('playerName')
            ));

            // If player is in the game, reconnect
            if ($player) {
                // TODO: Send game phase and player role
                return new Response('ReconnectSuccessful');
            }
            // If player is NOT in the game, refuse connection
            else {
                return new Response('MatchInProgress');
            }
        }

        // If game is finished, refuse connection
        else {
            return new Response('GameFinished');
        }
    }

    /**
     * @Route("/players-list-lobby", name="players-list-lobby")
     * @Method("POST")
     */
    public function playersListLobbyAction(Request $request)
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
                    $players[$randomNumber]->setRole('seer');
                }
                else if ($i == 2) {
                    $players[$randomNumber]->setRole('doctor');
                }
                else if ($i == 3) {
                    $players[$randomNumber]->setRole('werewolf');
                }
                else if ($i == 4) {
                    $players[$randomNumber]->setRole('werewolf');
                }
            }
        }
        $em->flush();

        // Assign roles to all other players
        $players = $em->getRepository(Game::class)->findBy(array('roomId' => $request->request->get('roomId') ));

        foreach ($players as $player) {
            if ($player->getRole() == 'unspecified') {
                $player->setRole('villager');
            }
        }
        $em->flush();

        $room->setGameStatus('inProgress');
        $room->setGamePhase('werewolves');
        $em->flush();

        return new Response('GameStarted');
    }

    /**
     * @Route("/add-players", name="add-players")
     * @Method("POST")
     */
    public function addPlayersAction(Request $request)
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

        // TODO: Make add 6 players logic better
        // Join game 6 times if player doesn't already exist
        for ($i = 2; $i <= 7; $i++) {
            $player = $em->getRepository(Game::class)->findBy(array(
                'roomId' => $request->request->get('roomId'),
                'playerName' => $request->request->get('playerName').$i
            ));
            if(!$player) {
                $game = new Game();
                $game->setRoomId($request->request->get('roomId'));
                $game->setPlayerName($request->request->get('playerName').$i);
                $game->setRole('unspecified');
                $game->setPlayerStatus('alive');
                $game->setAction('');

                $em->persist($game);
                $em->flush();
            }
        }

        return new Response('PlayersAdded');
    }

    /**
     * @Route("/fetch-role", name="fetch-role")
     * @Method("POST")
     */
    public function fetchRoleAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $player = $em->getRepository(Game::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $request->request->get('playerName')
        ));

        if(!$player) {
            return new Response('PlayerNotFound');
        }

        return new Response($player->getRole());
    }

    /**
     * @Route("/get-phase", name="get-phase")
     * @Method("POST")
     */
    public function getPhaseAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));

        return new Response($room->getGamePhase());
//        return new Response("doctor");
    }

    /**
     * @Route("/fetch-count", name="fetch-count")
     * @Method("POST")
     */
    public function fetchCountAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $werewolves = $em->getRepository(Game::class)->findBy(array(
            'roomId' => $request->request->get('roomId'),
            'role' => 'werewolf'
        ));

        $players = $em->getRepository(Game::class)->findBy(array(
            'roomId' => $request->request->get('roomId'),
        ));

        // Return werewolves and villagers (villagers = total number of players - number of werewolves)
        return new Response(count($werewolves).'||'.(count($players)-count($werewolves)));
    }

    /**
     * @Route("/players-list-werewolf", name="players-list-werewolf")
     * @Method("POST")
     */
    public function playersListWerewolfAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $players = $em->createQueryBuilder()
                        ->select('player')
                        ->from(Game::class, 'player')
                        ->where('player.roomId = :roomId')
                        ->setParameter('roomId', $request->request->get('roomId'))
                        ->andWhere('player.role != :role')
                        ->setParameter('role', "werewolf")
                        ->andWhere('player.playerStatus = :status')
                        ->setParameter('status', "alive")
                        ->getQuery()
                        ->getResult();

        foreach ($players as $player) {
            $oldName = $player->getPlayerName();
            $votes = $em->createQueryBuilder()
                ->select('player')
                ->from(Game::class, 'player')
                ->where('player.roomId = :roomId')
                ->setParameter('roomId', $request->request->get('roomId'))
                ->andWhere('player.role = :role')
                ->setParameter('role', "werewolf")
                ->andWhere('player.playerStatus = :status')
                ->setParameter('status', "alive")
                ->andWhere('player.action = :action')
                ->setParameter('action', $oldName)
                ->getQuery()
                ->getResult();
            $votesCount = count($votes);
            $player->setPlayerName($oldName.' ('.$votesCount.')');
        }

        $encoders = array(new XmlEncoder(), new JsonEncoder());
        $normalizers = array(new ObjectNormalizer());

        $serializer = new Serializer($normalizers, $encoders);

        $jsonContent = $serializer->serialize($players, 'json');

        return new Response($jsonContent);
    }

    /**
     * @Route("/werewolf-vote", name="werewolf-vote")
     * @Method("POST")
     */
    public function werewolfVoteAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $player = $em->getRepository(Game::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $request->request->get('playerName')
        ));

        $player->setAction($request->request->get('action'));

        $em->flush();

        return new Response('WerewolfVoteSuccessful');
    }

    /**
     * @Route("/werewolf-confirm", name="werewolf-confirm")
     * @Method("POST")
     */
    public function werewolfConfirmAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $werewolves = $em->getRepository(Game::class)->findBy(array(
            'roomId' => $request->request->get('roomId'),
            'role' => "werewolf",
            'playerStatus' => "alive"
        ));

        // Both werewolves must target the same villager
        $target = "";
        foreach ($werewolves as $werewolf) {
            if ($target == "") {
                $target = $werewolf->getAction();
            }
            else if ($target != $werewolf->getAction()) {
                return new Response('MustTargetSameVillager');
            }
        }

        // If neither werewolf selected target,
        // technically they have the same target
        // and here we return NoVotes
        $villager = $em->getRepository(Game::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $target
        ));

        if (!$villager) {
            return new Response("NoVotes");
        }

        $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
        $room->setGamePhase("doctor");

        $em->flush();

        return new Response('KillSuccessful');
    }

    /**
     * @Route("/players-list-doctor", name="players-list-doctor")
     * @Method("POST")
     */
    public function playersListDoctorAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $players = $em->createQueryBuilder()
            ->select('player')
            ->from(Game::class, 'player')
            ->where('player.roomId = :roomId')
            ->setParameter('roomId', $request->request->get('roomId'))
            ->andWhere('player.playerStatus = :status')
            ->setParameter('status', "alive")
            ->getQuery()
            ->getResult();

        foreach ($players as $player) {
            $oldName = $player->getPlayerName();
            $votes = $em->createQueryBuilder()
                ->select('player')
                ->from(Game::class, 'player')
                ->where('player.roomId = :roomId')
                ->setParameter('roomId', $request->request->get('roomId'))
                ->andWhere('player.role = :role')
                ->setParameter('role', "doctor")
                ->andWhere('player.playerStatus = :status')
                ->setParameter('status', "alive")
                ->andWhere('player.action = :action')
                ->setParameter('action', $oldName)
                ->getQuery()
                ->getResult();
            $votesCount = count($votes);
            $player->setPlayerName($oldName.' ('.$votesCount.')');
        }

        $encoders = array(new XmlEncoder(), new JsonEncoder());
        $normalizers = array(new ObjectNormalizer());

        $serializer = new Serializer($normalizers, $encoders);

        $jsonContent = $serializer->serialize($players, 'json');

        return new Response($jsonContent);
    }

    /**
     * @Route("/doctor-vote", name="doctor-vote")
     * @Method("POST")
     */
    public function doctorVoteAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $player = $em->getRepository(Game::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $request->request->get('playerName')
        ));

        $player->setAction($request->request->get('action'));

        $em->flush();

        return new Response('DoctorVoteSuccessful');
    }

    /**
     * @Route("/doctor-confirm", name="doctor-confirm")
     * @Method("POST")
     */
    public function doctorConfirmAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $doctor = $em->getRepository(Game::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'role' => "doctor",
            'playerStatus' => "alive"
        ));

        // TODO: Check if doctor is alive

        // Check if doctor voted
        if ($doctor->getAction() == "") {
            return new Response('NoVote');
        }

        $werewolves = $em->getRepository(Game::class)->findBy(array(
            'roomId' => $request->request->get('roomId'),
            'role' => "werewolf",
            'playerStatus' => "alive"
        ));

        $werewolvesTarget = $werewolves[0]->getAction();
        $doctorTarget = $doctor->getAction();
        if ($doctorTarget == $werewolvesTarget) {
            $player = $em->getRepository(Game::class)->findOneBy(array(
                'roomId' => $request->request->get('roomId'),
                'playerName' => $doctorTarget
            ));
            $player->setPlayerStatus("revived");
        }
        else {
            $player = $em->getRepository(Game::class)->findOneBy(array(
                'roomId' => $request->request->get('roomId'),
                'playerName' => $werewolvesTarget
            ));
            $player->setPlayerStatus("dead");
        }

        // Clear actions
        foreach ($werewolves as $werewolf) {
            $werewolf->setAction("");
        }
        $doctor->setAction("");
        $em->flush();

        $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
        $room->setGamePhase("seer");

        $em->flush();

        return new Response('HealSuccessful');
    }

    /**
     * @Route("/players-list-seer", name="players-list-seer")
     * @Method("POST")
     */
    public function playersListSeerAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $players = $em->createQueryBuilder()
            ->select('player')
            ->from(Game::class, 'player')
            ->where('player.roomId = :roomId')
            ->setParameter('roomId', $request->request->get('roomId'))
            ->andWhere('player.role != :role')
            ->setParameter('role', "seer")
            ->andWhere('player.playerStatus = :status')
            ->setParameter('status', "alive")
            ->getQuery()
            ->getResult();

        $encoders = array(new XmlEncoder(), new JsonEncoder());
        $normalizers = array(new ObjectNormalizer());

        $serializer = new Serializer($normalizers, $encoders);

        $jsonContent = $serializer->serialize($players, 'json');

        return new Response($jsonContent);
    }

    /**
     * @Route("/seer-vote", name="seer-vote")
     * @Method("POST")
     */
    public function seerVoteAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $player = $em->getRepository(Game::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $request->request->get('action')
        ));

        $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));

        $room->setGamePhase('day');
        $em->flush();

        if ($player->getRole() == "werewolf") {
            return new Response($player->getPlayerName()." is a WEREWOLF!");
        }

        return new Response($player->getPlayerName()." is not a werewolf");
    }

    /**
     * @Route("/players-list-day", name="players-list-day")
     * @Method("POST")
     */
    public function playersListDayAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $players = $em->createQueryBuilder()
            ->select('player')
            ->from(Game::class, 'player')
            ->where('player.roomId = :roomId')
            ->setParameter('roomId', $request->request->get('roomId'))
            ->andWhere('player.playerName != :playerName')
            ->setParameter('playerName', $request->request->get('playerName'))
            ->andWhere('player.playerStatus = :status')
            ->setParameter('status', "alive")
            ->getQuery()
            ->getResult();

        foreach ($players as $player) {
            $oldName = $player->getPlayerName();
            $votes = $em->createQueryBuilder()
                ->select('player')
                ->from(Game::class, 'player')
                ->where('player.roomId = :roomId')
                ->setParameter('roomId', $request->request->get('roomId'))
                ->andWhere('player.playerStatus = :status')
                ->setParameter('status', "alive")
                ->andWhere('player.action = :action')
                ->setParameter('action', $oldName)
                ->getQuery()
                ->getResult();
            $votesCount = count($votes);
            $player->setPlayerName($oldName.' ('.$votesCount.')');
        }

        $encoders = array(new XmlEncoder(), new JsonEncoder());
        $normalizers = array(new ObjectNormalizer());

        $serializer = new Serializer($normalizers, $encoders);

        $jsonContent = $serializer->serialize($players, 'json');

        return new Response($jsonContent);
    }

    /**
     * @Route("/day-vote", name="doctor-vote")
     * @Method("POST")
     */
    public function dayVoteAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $player = $em->getRepository(Game::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $request->request->get('playerName')
        ));

        $player->setAction($request->request->get('action'));

        $em->flush();

        return new Response('DayVoteSuccessful');
    }

    /**
     * @Route("/day-confirm", name="day-confirm")
     * @Method("POST")
     */
    public function dayConfirmAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $players = $em->getRepository(Game::class)->findBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerStatus' => "alive"
        ));

        // Check if all players voted
        foreach ($players as $player) {
            if ($player->getAction() == "") {
                return new Response('NoVote');
            }
        }

        // Count votes
        $maxVotes = 0;
        $maxVotedPlayer = "";
        foreach ($players as $player) {
            $votes = $em->createQueryBuilder()
                ->select('player')
                ->from(Game::class, 'player')
                ->where('player.roomId = :roomId')
                ->setParameter('roomId', $request->request->get('roomId'))
                ->andWhere('player.playerStatus = :status')
                ->setParameter('status', "alive")
                ->andWhere('player.action = :action')
                ->setParameter('action', $player->getPlayerName())
                ->getQuery()
                ->getResult();
            $votesCount = count($votes);
            if ($votesCount > $maxVotes) {
                $maxVotes = $votesCount;
                $maxVotedPlayer = $player->getPlayerName();
            }
        }

        // Kill player
        $player = $em->getRepository(Game::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $maxVotedPlayer
        ));
        $em->remove($player);

        // Clear actions
        foreach ($players as $player) {
            $player->setAction("");
        }

        $em->flush();

        $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
        $room->setGamePhase("werewolves");

        $em->flush();

        return new Response('VoteSuccessful');
    }



//    /**
//     * @Route("/{genusName}")
//     */
//    public function showAction($genusName)
//    {
//
//        return new Response('The genus: '.$genusName);
//    }
//
//    /**
//     * @Route("/genus2/{genusName}")
//     */
//    public function showAction2($genusName)
//    {
//        $notes = [
//            'Octopus asked me a riddle, outsmarted me',
//            'I counted 8 legs... as the wrapped around me',
//            'Inked!'
//        ];
//
//        return $this->render('genus/show2.html.twig', [
//            'name' => $genusName,
//            'notes' => $notes
//        ]);
//
//    }
//
//    /**
//     * @Route("/genus3/{genusName}")
//     */
//    public function showAction3($genusName)
//    {
//
//        return $this->render('genus/show3.html.twig', [
//            'name' => $genusName,
//        ]);
//
//    }
//
//    /**
//     * @Route("/genus/{genusName}/notes", name="genus_show_notes")
//     * @Method("GET")
//     */
//    public function getNotesAction()
//    {
//        $notes = [
//            ['id' => 1, 'username' => 'AquaPelham', 'avatarUri' => '/images/leanna.jpeg', 'note' => 'Octopus asked me a riddle, outsmarted me', 'date' => 'Dec. 10, 2015'],
//            ['id' => 2, 'username' => 'AquaWeaver', 'avatarUri' => '/images/ryan.jpeg', 'note' => 'I counted 8 legs... as they wrapped around me', 'date' => 'Dec. 1, 2015'],
//            ['id' => 3, 'username' => 'AquaPelham', 'avatarUri' => '/images/leanna.jpeg', 'note' => 'Inked!', 'date' => 'Aug. 20, 2015'],
//        ];
//
//        $data = [
//            'notes' => $notes,
//        ];
//
//        return new JsonResponse($data);
//
//
//
//    }

}
