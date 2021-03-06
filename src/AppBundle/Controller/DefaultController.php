<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Player;
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
     * @Route("/", name="root")
     */
    public function indexAction(Request $request)
    {
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR,
        ]);
    }

    /**
     * @Route("/werewolf", name="homepage")
     * @Method("GET")
     */
    public function homepageAction(Request $request) {
        return $this->render('default/werewolf.html.twig');
    }

    /**
     * @Route("/server-check", name="server-check")
     * @Method("POST")
     */
    public function serverCheckAction()
    {
        return new Response("OK");
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
        $room->setGamePhase('lobby');

        $em->persist($room);
        $em->flush();

        $id = $room->getId();

        $player = new Player();
        $player->setRoomId($id);
        $player->setPlayerName($request->request->get('playerName'));
        $player->setRole('unspecified');
        $player->setPlayerStatus('alive');
        $player->setAction('');

        $em->persist($player);
        $em->flush();

        return new Response($id);
    }

    /**
     * @Route("/lobby-players-list", name="lobby-players-list")
     * @Method("POST")
     */
    public function lobbyPlayersListAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $players = $em->getRepository(Player::class)->findBy(array('roomId' => $request->request->get('roomId') ));

        $encoders = array(new XmlEncoder(), new JsonEncoder());
        $normalizers = array(new ObjectNormalizer());

        $serializer = new Serializer($normalizers, $encoders);

        $jsonContent = $serializer->serialize($players, 'json');

        return new Response($jsonContent);
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
    }

    /**
     * @Route("/lobby-start-game", name="lobby-start-game")
     * @Method("POST")
     */
    public function lobbyStartGameAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
        $players = $em->getRepository(Player::class)->findBy(array('roomId' => $request->request->get('roomId') ));

        // Minimum number of players = 7, maximum = 16
        $numberOfPlayers = count($players);
        if ($numberOfPlayers < 7) {
            return new Response('MinimumPlayers7');
        }
        else if ($numberOfPlayers > 16) {
            return new Response('MaximumPlayers16');
        }

        // Generate roles to 4 random players
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

        // Assign roles to all other players
        foreach ($players as $player) {
            if ($player->getRole() == 'unspecified') {
                $player->setRole('villager');
            }
        }

        $room->setGameStatus('inProgress');
        $room->setGamePhase('showRoles');
        $em->flush();

        return new Response('GameStarted');
    }

    /**
     * @Route("/lobby-add-players", name="lobby-add-players")
     * @Method("POST")
     */
    public function lobbyAddPlayersAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        // Join game 6 times if player doesn't already exist
        for ($i = 2; $i <= 7; $i++) {
            $player = $em->getRepository(Player::class)->findOneBy(array(
                'roomId' => $request->request->get('roomId'),
                'playerName' => $request->request->get('playerName').$i
            ));
            if(!$player) {
                $game = new Player();
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
     * @Route("/lobby-remove-player", name="lobby-remove-player")
     * @Method("POST")
     */
    public function lobbyRemovePlayerAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        // Remove player from the room
        $player = $em->getRepository(Player::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $request->request->get('playerName')
        ));

        $em->remove($player);
        $em->flush();

        // If there are no players in the room, delete it
        $players = $em->getRepository(Player::class)->findBy(array(
            'roomId' => $request->request->get('roomId')
        ));

        if (!$players) {
            $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
            $em->remove($room);
            $em->flush();
            return new Response('NoMorePlayers');
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

        // If room doesn't exist
        if (!$room) {
            return new Response('RoomNotFound');
        }

        // If game is new, and name is not in use, join
        if ($room->getGameStatus() == 'new') {

            // If name is already in use
            $name = $em->getRepository(Player::class)->findOneBy(array(
                'roomId' => $request->request->get('roomId'),
                'playerName' => $request->request->get('playerName')
            ));

            if ($name) {
                return new Response('NameInUse');
            }

            // Join game
            $player = new Player();
            $player->setRoomId($request->request->get('roomId'));
            $player->setPlayerName($request->request->get('playerName'));
            $player->setRole('unspecified');
            $player->setPlayerStatus('alive');
            $player->setAction('');

            $em->persist($player);
            $em->flush();
            return new Response('JoinGameSuccessful');
        }

        // If game is in progress, and player is in the game, reconnect
        else if ($room->getGameStatus() == 'inProgress') {
            $player = $em->getRepository(Player::class)->findOneBy(array(
                'roomId' => $request->request->get('roomId'),
                'playerName' => $request->request->get('playerName')
            ));

            // If player is in the game, reconnect
            if ($player) {
                if ($room->getGamePhase() != 'showRoles') {
                    $player->setAction('reconnecting');
                    $em->flush();
                }
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
     * @Route("/fetch-role", name="fetch-role")
     * @Method("POST")
     */
    public function fetchRoleAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $player = $em->getRepository(Player::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $request->request->get('playerName')
        ));

        return new Response($player->getRole());
    }

    /**
     * @Route("/show-roles-ready", name="show-roles-ready")
     * @Method("POST")
     */
    public function showRolesReadyAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        // If player action == reconnecting, send ready
        $player = $em->getRepository(Player::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $request->request->get('playerName')
        ));

        if ($player->getAction() == 'reconnecting') {
            $player->setAction('');
            $em->flush();
            return new Response('Reconnecting');
        }

        // Otherwise, count ready players and set current user action to ready if it is not already set
        $players = $em->getRepository(Player::class)->findBy(array(
            'roomId' => $request->request->get('roomId'),
        ));

        $readyCounter = 0;
        foreach ($players as $player) {
            if ($player->getAction() == 'ready') {
                $readyCounter++;
            }
            else if ($player->getPlayerName() == $request->request->get('playerName')) {
                $player->setAction('ready');
                $em->flush();
                $readyCounter++;
            }
        }

        // If all players are ready, clear all actions and start game, otherwise send number of ready players
        if ($readyCounter == count($players)) {
            foreach ($players as $player) {
                $player->setAction('');
            }
            $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
            $room->setGamePhase('werewolves');
            $em->flush();
            return new Response('EveryoneReady');
        }

        return new Response('Players ready: ' . $readyCounter . '/' . count($players));
    }

    /**
     * @Route("/fetch-count", name="fetch-count")
     * @Method("POST")
     */
    public function fetchCountAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $players = $em->createQueryBuilder()
            ->select('player')
            ->from(Player::class, 'player')
            ->where('player.roomId = :roomId')
            ->setParameter('roomId', $request->request->get('roomId'))
            ->andWhere('player.playerStatus = :status1 OR player.playerStatus = :status2')
            ->setParameter('status1', "alive")
            ->setParameter('status2', "revived")
            ->getQuery()
            ->getResult();

        $werewolvesCount = 0;
        foreach ($players as $player) {
            if ($player->getRole() == "werewolf") {
                $werewolvesCount++;
            }
        }

        // Return werewolves and villagers (villagers = total number of players - number of werewolves)
        return new Response($werewolvesCount.'||'.(count($players)-$werewolvesCount));
    }

    /**
     * @Route("/werewolf-players-list", name="werewolf-players-list")
     * @Method("POST")
     */
    public function werewolfPlayersListAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $players = $em->createQueryBuilder()
                        ->select('player')
                        ->from(Player::class, 'player')
                        ->where('player.roomId = :roomId')
                        ->setParameter('roomId', $request->request->get('roomId'))
                        ->andWhere('player.role != :role')
                        ->setParameter('role', "werewolf")
                        ->andWhere('player.playerStatus = :status')
                        ->setParameter('status', "alive")
                        ->getQuery()
                        ->getResult();

        $werewolves = $em->createQueryBuilder()
            ->select('player')
            ->from(Player::class, 'player')
            ->where('player.roomId = :roomId')
            ->setParameter('roomId', $request->request->get('roomId'))
            ->andWhere('player.role = :role')
            ->setParameter('role', "werewolf")
            ->andWhere('player.playerStatus = :status')
            ->setParameter('status', "alive")
            ->getQuery()
            ->getResult();

        foreach ($players as $player) {
            $oldName = $player->getPlayerName();
            $votesCount = 0;
            foreach ($werewolves as $werewolf) {
                if ($werewolf->getAction() == $oldName) {
                    $votesCount++;
                }
            }
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
        $player = $em->getRepository(Player::class)->findOneBy(array(
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

        $werewolves = $em->getRepository(Player::class)->findBy(array(
            'roomId' => $request->request->get('roomId'),
            'role' => "werewolf",
            'playerStatus' => "alive"
        ));

        // Both werewolves must vote and both have to target the same villager
        $target = "";

        foreach ($werewolves as $werewolf) {
            if ($werewolf->getAction() == "") {
                return new Response("NoVotes");
            }
            else if ($target == "") {
                $target = $werewolf->getAction();
            }
            else if ($target != $werewolf->getAction()) {
                return new Response('MustTargetSameVillager');
            }
        }

        // Set target villager playerStatus to killed and room gamePhase to doctor, seer or day
        $villager = $em->getRepository(Player::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $target
        ));
        $villager->setPlayerStatus("killed");

        // Clear werewolves actions
        foreach ($werewolves as $werewolf) {
            $werewolf->setAction("");
        }

        // Fetch all alive players (killed player is not yet flushed
        // which means that this search will include killed doctor or seer)
        $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
        $players = $em->getRepository(Player::class)->findBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerStatus' => "alive"
        ));

        // Set default game phase to day
        $room->setGamePhase("day");

        // If doctor is alive, change game phase
        foreach ($players as $player) {
            if ($player->getRole() == "doctor") {
                $room->setGamePhase("doctor");
                break;
            }
        }
        // If doctor was not alive, look for seer
        if ($room->getGamePhase() == "day") {
            foreach ($players as $player) {
                if ($player->getRole() == "seer") {
                    $room->setGamePhase("seer");
                    break;
                }
            }
        }

        $em->flush();

        return new Response('KillSuccessful');
    }

    /**
     * @Route("/doctor-players-list", name="doctor-players-list")
     * @Method("POST")
     */
    public function doctorPlayersListAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $players = $em->createQueryBuilder()
            ->select('player')
            ->from(Player::class, 'player')
            ->where('player.roomId = :roomId')
            ->setParameter('roomId', $request->request->get('roomId'))
            ->andWhere('player.playerStatus = :status1 OR player.playerStatus = :status2')
            ->setParameter('status1', "alive")
            ->setParameter('status2', "killed")
            ->getQuery()
            ->getResult();

        $doctors = $em->createQueryBuilder()
            ->select('player')
            ->from(Player::class, 'player')
            ->where('player.roomId = :roomId')
            ->setParameter('roomId', $request->request->get('roomId'))
            ->andWhere('player.role = :role')
            ->setParameter('role', "doctor")
            ->andWhere('player.playerStatus = :status1 OR player.playerStatus = :status2')
            ->setParameter('status1', "alive")
            ->setParameter('status2', "killed")
            ->getQuery()
            ->getResult();

        foreach ($players as $player) {
            $oldName = $player->getPlayerName();
            $votesCount = 0;
            foreach ($doctors as $doctor) {
                if ($doctor->getAction() == $oldName) {
                    $votesCount++;
                }
            }
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

        $player = $em->getRepository(Player::class)->findOneBy(array(
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

        // Check if doctor voted
        $doctor = $em->getRepository(Player::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'role' => "doctor"
        ));
        if ($doctor->getAction() == "") {
            return new Response('NoVote');
        }

        // If target is killed, change playerStatus to revived. Clear doctor action
        $target = $em->getRepository(Player::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $doctor->getAction()
        ));
        if ($target->getPlayerStatus() == "killed") {
            $target->setPlayerStatus('revived');
        }
        $doctor->setAction('');

        // Check if seer is alive and setGamePhase accordingly
        // (revived is not yet flushed if doctor healed seer)
        $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
        $seer = $em->createQueryBuilder()
            ->select('player')
            ->from(Player::class, 'player')
            ->where('player.roomId = :roomId')
            ->setParameter('roomId', $request->request->get('roomId'))
            ->andWhere('player.role = :role')
            ->setParameter('role', "seer")
            ->andWhere('player.playerStatus = :status1 OR player.playerStatus = :status2')
            ->setParameter('status1', "alive")
            ->setParameter('status2', "killed")
            ->getQuery()
            ->getResult();

        if ($seer) {
            $room->setGamePhase("seer");
        }
        else {
            $room->setGamePhase("day");
        }

        $em->flush();

        return new Response('HealSuccessful');
    }

    /**
     * @Route("/seer-players-list", name="seer-players-list")
     * @Method("POST")
     */
    public function seerPlayersListAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $players = $em->createQueryBuilder()
            ->select('player')
            ->from(Player::class, 'player')
            ->where('player.roomId = :roomId')
            ->setParameter('roomId', $request->request->get('roomId'))
            ->andWhere('player.role != :role')
            ->setParameter('role', "seer")
            ->andWhere('player.playerStatus = :status1 OR player.playerStatus = :status2 OR player.playerStatus = :status3')
            ->setParameter('status1', "alive")
            ->setParameter('status2', "killed")
            ->setParameter('status3', "revived")
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

        $player = $em->getRepository(Player::class)->findOneBy(array(
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
     * @Route("/day-players-list", name="day-players-list")
     * @Method("POST")
     */
    public function playersListDayAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        // All players who are alive or revived and not current user
        $players = $em->createQueryBuilder()
            ->select('player')
            ->from(Player::class, 'player')
            ->where('player.roomId = :roomId')
            ->setParameter('roomId', $request->request->get('roomId'))
            ->andWhere('player.playerName != :playerName')
            ->setParameter('playerName', $request->request->get('playerName'))
            ->andWhere('player.playerStatus = :status1 OR player.playerStatus = :status2')
            ->setParameter('status1', "alive")
            ->setParameter('status2', "revived")
            ->getQuery()
            ->getResult();

        $villagers = $em->createQueryBuilder()
            ->select('player')
            ->from(Player::class, 'player')
            ->where('player.roomId = :roomId')
            ->setParameter('roomId', $request->request->get('roomId'))
            ->andWhere('player.playerStatus = :status1 OR player.playerStatus = :status2')
            ->setParameter('status1', "alive")
            ->setParameter('status2', "revived")
            ->getQuery()
            ->getResult();

        foreach ($players as $player) {
            $oldName = $player->getPlayerName();
            $votesCount = 0;
            foreach ($villagers as $villager) {
                if ($villager->getAction() == $oldName) {
                    $votesCount++;
                }
            }
            $player->setPlayerName($oldName.' ('.$votesCount.')');
        }

        $encoders = array(new XmlEncoder(), new JsonEncoder());
        $normalizers = array(new ObjectNormalizer());

        $serializer = new Serializer($normalizers, $encoders);

        $jsonContent = $serializer->serialize($players, 'json');

        return new Response($jsonContent);
    }

    /**
     * @Route("/day-vote", name="day-vote")
     * @Method("POST")
     */
    public function dayVoteAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $player = $em->getRepository(Player::class)->findOneBy(array(
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

        // Check if all alive and revived players voted
        $players = $em->createQueryBuilder()
            ->select('player')
            ->from(Player::class, 'player')
            ->where('player.roomId = :roomId')
            ->setParameter('roomId', $request->request->get('roomId'))
            ->andWhere('player.playerStatus = :status1 OR player.playerStatus = :status2')
            ->setParameter('status1', "alive")
            ->setParameter('status2', "revived")
            ->getQuery()
            ->getResult();

        foreach ($players as $player) {
            if ($player->getAction() == "") {
                return new Response('NoVote');
            }
        }

        // Count votes
        $maxVotes = 0;
        $secondMaxVotes = 0;
        $maxVotedPlayer = "";
        foreach ($players as $player) {
            $votes = $em->createQueryBuilder()
                ->select('player')
                ->from(Player::class, 'player')
                ->where('player.roomId = :roomId')
                ->setParameter('roomId', $request->request->get('roomId'))
                ->andWhere('player.playerStatus = :status1 OR player.playerStatus = :status2')
                ->setParameter('status1', "alive")
                ->setParameter('status2', "revived")
                ->andWhere('player.action = :action')
                ->setParameter('action', $player->getPlayerName())
                ->getQuery()
                ->getResult();
            $votesCount = count($votes);
            if ($votesCount >= $maxVotes) {
                $secondMaxVotes = $maxVotes;
                $maxVotes = $votesCount;
                $maxVotedPlayer = $player->getPlayerName();
            }
        }
        // If two or more players have the same number of votes
        if ($maxVotes == $secondMaxVotes) {
            return new Response('SameNumberOfVotes');
        }

        // Kill max voted player and clear all actions
        foreach ($players as $player) {
            if ($player->getPlayerName() == $maxVotedPlayer) {
                $player->setPlayerStatus("dead");
            }
            $player->setAction("");
        }
        $em->flush();

        // Change playerStatus killed => dead and revived => alive and calculate new gamePhase
        $room = $em->getRepository(Room::class)->find($request->request->get('roomId'));
        $players = $em->createQueryBuilder()
            ->select('player')
            ->from(Player::class, 'player')
            ->where('player.roomId = :roomId')
            ->setParameter('roomId', $request->request->get('roomId'))
            ->andWhere('player.playerStatus != :status')
            ->setParameter('status', "dead")
            ->getQuery()
            ->getResult();

        foreach ($players as $player) {
            if ($player->getPlayerStatus() == "killed") {
                $player->setPlayerStatus('dead');
            }
            else if ($player->getPlayerStatus() == "revived") {
                $player->setPlayerStatus('alive');
            }
        }

        // If all werewolves are dead gamePhase = VillagersVictory
        // else if number of villagers == number of werewolves or total number of players <= 4, WerewolvesVictory
        $werewolvesCount = 0;
        $villagersCount = 0;
        foreach ($players as $player) {
            if ($player->getRole() == "werewolf" && $player->getPlayerStatus() == "alive") {
                $werewolvesCount++;
            }
            else if (( ($player->getRole() == "villager" || ($player->getRole() == "doctor") || ($player->getRole() == "seer")) && $player->getPlayerStatus() == "alive")) {
                $villagersCount++;
            }
        }
        if ($werewolvesCount == 0) {
            $room->setGamePhase('villagersVictory');
            $room->setGameStatus('finished');
        }
        else if ($villagersCount == $werewolvesCount) {
            $room->setGamePhase('werewolvesVictory');
            $room->setGameStatus('finished');
        }
        else if (count($players) <= 4) {
            $room->setGamePhase('werewolvesVictory');
            $room->setGameStatus('finished');
        }
        else {
            $room->setGamePhase('werewolves');
        }

        $em->flush();

        return new Response('VoteSuccessful');
    }

    /**
     * @Route("/fetch-player-status", name="fetch-player-status")
     * @Method("POST")
     */
    public function fetchPlayerStatusAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $player = $em->getRepository(Player::class)->findOneBy(array(
            'roomId' => $request->request->get('roomId'),
            'playerName' => $request->request->get('playerName')
        ));

        return new Response($player->getPlayerStatus());
    }

    /**
     * @Route("/fetch-night-recap", name="fetch-night-recap")
     * @Method("POST")
     */
    public function fetchNightRecapAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $players = $em->getRepository(Player::class)->findBy(array(
            'roomId' => $request->request->get('roomId'),
        ));

        foreach ($players as $player) {
            if ($player->getPlayerStatus() == "killed") {
                return new Response($player->getPlayerName()."||killed");
            }
            else if ($player->getPlayerStatus() == "revived") {
                return new Response($player->getPlayerName()."||revived");
            }
        }
        return new Response('error');
    }
}
