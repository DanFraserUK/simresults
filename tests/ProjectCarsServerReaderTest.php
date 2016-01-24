    <?php
use Simresults\Data_Reader_AssettoCorsaServerJson;
use Simresults\Data_Reader;
use Simresults\Session;
use Simresults\Participant;

/**
 * Tests for the Project Cars Server reader
 *
 *
 * @author     Maurice van der Star <mauserrifle@gmail.com>
 * @copyright  (c) 2013 Maurice van der Star
 * @license    http://opensource.org/licenses/ISC
 */
class ProjectCarsServerReaderTest extends PHPUnit_Framework_TestCase {

    /**
     * Set error reporting
     *
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp()
    {
        error_reporting(E_ALL);
    }


    /**
     * Test exception when no data is supplied
     *
     * @expectedException Simresults\Exception\CannotReadData
     */
    public function testCreatingNewAssettoCorsaReaderWithInvalidData()
    {
        $reader = new Data_Reader_AssettoCorsaServerJson('Unknown data for reader');
    }


    /**
     * Test reading finish statusses and positions of race that was not
     * finished
     */
    public function testReadingStatussesAndPositionsOfRaceWithoutFinish()
    {
        // The path to the data source
        $file_path = realpath(__DIR__.
            '/logs/projectcars-server/race.without.finish.json');

        // Get the data reader for the given data source
        $session = Data_Reader::factory($file_path)->getSession(5);

        // Get participants
        $participants = $session->getParticipants();

        // Validate statusses
        foreach ($participants as $part)
        {
            $this->assertSame(Participant::FINISH_NONE,
                $part->getFinishStatus());
        }

        // Validation positions
        $this->assertSame('I am Reginald',
            $participants[0]->getDriver()->getName());
        $this->assertSame('xCrazydogx',
            $participants[4]->getDriver()->getName());

    }

    /**
     * Test  proper positions for qualify and practice. Drivers were not
     * properly positioned because we ordered mainly on 'results' data.
     * But apperently some drivers are missing so this tests that we do
     * not rely on this info any more for practice and qualify sessions
     */
    public function testReadingProperPositionsForNonRace()
    {
        // The path to the data source
        $file_path = realpath(__DIR__.
            '/logs/projectcars-server/race.without.finish.json');

        // Get the data reader for the given data source
        $sessions = Data_Reader::factory($file_path)->getSessions();

        // Get participants for practice 2
        $participants = $sessions[1]->getParticipants();

        $this->assertSame('ivanmille',
            $participants[0]->getDriver()->getName());

        // Validate Zockerbursche
        $this->assertSame('Zockerbursche',
            $participants[9]->getDriver()->getName());


        // Get participants for qualify
        $participants = $sessions[2]->getParticipants();

        $this->assertSame('ivanmille',
            $participants[0]->getDriver()->getName());

        // Validate patrok
        $this->assertSame('patrok1207³',
            $participants[9]->getDriver()->getName());
    }

    /**
     * Test reading the best laps from a log that has no events (thus no laps).
     * We fallback to "FastestLapTime" within the results data.
     */
    public function testReadingBestLapFromLogWithoutEvents()
    {
        // The path to the data source
        $file_path = realpath(__DIR__.
            '/logs/projectcars-server/stages.without.events.json');

        // Get the data reader for the given data source
        $sessions = Data_Reader::factory($file_path)->getSessions();

        // Get participants
        $participants = $sessions[0]->getParticipants();

        // Test the best lap
        $this->assertSame(119.417, $participants[0]->getBestLap()->getTime());
    }

    /**
     * Test reading  DNF states
     */
    public function testReadingDNFstates()
    {
        // The path to the data source
        $file_path = realpath(__DIR__.
            '/logs/projectcars-server/stages.without.events.json');

        // Get the data reader for the given data source
        $sessions = Data_Reader::factory($file_path)->getSessions();

        // Get participants of 10th session
        $participants = $sessions[9]->getParticipants();

        // Test DNF status
        $this->assertSame(Participant::FINISH_DNF,
            $participants[1]->getFinishStatus());

        // Get participants of last session
        $participants = $sessions[count($sessions)-1]->getParticipants();

        // Test retired status as DNF
        $this->assertSame('[CAV] F1_Racer68',
            $participants[4]->getDriver()->getName());
        $this->assertSame(Participant::FINISH_DNF,
            $participants[4]->getFinishStatus());
    }



    /**
     * Test no exceptions and proper participants on unknown/bad participant
     * ids. Participants will be collected using events as a fallback
     */
    public function testDataOnUnknownOrBadParticipantIds()
    {
        // The path to the data source
        $file_path = realpath(__DIR__.
            '/logs/projectcars-server/unknown.participant.ids.json');

        // Get the data reader for the given data source
        $sessions = Data_Reader::factory($file_path)->getSessions();

        // Get participants
        $participants = $sessions[0]->getParticipants();

        // Validate number of participants
        $this->assertSame(17, count($participants));

        // Validate human state
        $this->assertTrue($participants[0]->getDriver()->isHuman());
        $this->assertTrue($participants[1]->getDriver()->isHuman());
        $this->assertFalse($participants[2]->getDriver()->isHuman());
    }

    /**
     * Test no exceptions on another log missing participant ids
     */
    public function testDataOnUnknownOrBadParticipantIds2()
    {
        // The path to the data source
        $file_path = realpath(__DIR__.
            '/logs/projectcars-server/unknown.participant.ids2.json');

        // Get the data reader for the given data source
        $sessions = Data_Reader::factory($file_path)->getSessions();
    }

    /**
     * Test reading a log with two forward slashes in the content that caused
     * bad log cleaning
     */
    public function testReadingLogContainingContentWithForwardSlashes()
    {
        // The path to the data source
        $file_path = realpath(__DIR__.
            '/logs/projectcars-server/forward.slashes.in.content.json');

        $sessions = Data_Reader::factory($file_path)->getSessions();
        $this->assertSame(8, count($sessions));
    }

    /**
     * Test whether proper cut times are read. This tests a bug fix where too
     * many cut ends were read. Fixed using break in for loop when END for cut
     * is found.
     */
    public function testProperCutTimesWithAlotOfCutEvents()
    {
        // The path to the data source
        $file_path = realpath(__DIR__.
            '/logs/projectcars-server/race.with.alot.of.cuts.json');

        // Get the data reader for the given data source
        // TODO: Why is this 5? Can we exclude the other sessions because of
        // having empty data?
        $session = Data_Reader::factory($file_path)->getSession(5);

        // Get participants
        $participants = $session->getParticipants();

        // Get laps of second participant
        $laps = $participants[1]->getLaps();

        // Validate cuts
        $this->assertSame(1.434, $laps[1]->getCutsTime());
    }



    /***
    **** Below tests use 1 race log file
    ***/

    /**
     * Test reading the first 5 sessions
     */
    public function testReadingMultipleSessions()
    {
        $tests = array(
            array(
                'type'     => Session::TYPE_PRACTICE,
                'max_laps' => 15,
                'time'     => 1446146942,
            ),
            array(
                'type'     => Session::TYPE_PRACTICE,
                'max_laps' => 15,
                'time'     => 1446147862,
            ),
            array(
                'type'     => Session::TYPE_QUALIFY,
                'max_laps' => 15,
                'time'     => 1446148782,
            ),
            array(
                'type'     => Session::TYPE_WARMUP,
                'max_laps' => 5,
                'time'     => 1446149702,
            ),
            array(
                'type'     => Session::TYPE_RACE,
                'max_laps' => 7,
                'time'     => 1446150022,
            ),
        );


        // Get sessions and test them
        $sessions = $this->getWorkingReader()->getSessions();
        foreach ($tests as $test_key => $test)
        {
            $session = $sessions[$test_key];

            //-- Validate
            $this->assertSame($test['type'], $session->getType());
            $this->assertSame($test['max_laps'], $session->getMaxLaps());
            $this->assertSame($test['time'],
                $session->getDate()->getTimestamp());
            $this->assertSame('UTC',
                $session->getDate()->getTimezone()->getName());

            $this->assertSame(array(
                'DamageType'                  => 3,
                'FuelUsageType'               => 0,
                'PenaltiesType'               => 0,
                'ServerControlsSetup'         => 1,
                'ServerControlsTrack'         => 1,
                'ServerControlsVehicle'       => 0,
                'ServerControlsVehicleClass'  => 1,
                'TireWearType'                => 6,
                ),
                $session->getOtherSettings());
        }


    }

    /**
     * Test reading the server of a session
     */
    public function testReadingSessionServer()
    {
        // Get the server
        $server = $this->getWorkingReader()->getSession()->getServer();

        // Validate server
        $this->assertSame('[ITA]www.racingnetwork.eu', $server->getName());
        // TODO: Settings
    }

    /**
     * Test reading the game of a session
     */
    public function testReadingSessionGame()
    {
        // Get the game
        $game = $this->getWorkingReader()->getSession()->getGame();

        // Validate game
        $this->assertSame('Project Cars', $game->getName());
    }

    /**
     * Test reading the track of a session
     */
    public function testReadingSessionTrack()
    {
        // Get the track
        $track = $this->getWorkingReader()->getSession()->getTrack();

        // Validate track
        $this->assertSame('Mazda Raceway Laguna Seca', $track->getVenue());
    }

    /**
     * Test reading the participants of a session
     */
    public function testReadingSessionParticipants()
    {
        // Get participants
        $participants = $this->getWorkingReader()->getSession(5)
            ->getParticipants();


        // Validatet he number because we filter all who have no events
        $this->assertSame(10, count($participants));


        /**
         * Validate first participant
         */
        $participant = $participants[0];

        $this->assertSame('ItchyTrigaFinga',
                          $participant->getDriver()->getName());
        $this->assertSame('Ford Mustang Cobra TransAm',
                          $participant->getVehicle()->getName());
        $this->assertSame('Trans-Am',
                          $participant->getVehicle()->getClass());
        $this->assertSame('76561198015591839',
                          $participant->getDriver()->getDriverId());
        $this->assertTrue($participant->getDriver()->isHuman());
        $this->assertSame(1, $participant->getPosition());
        $this->assertSame(12, $participant->getGridPosition());
        $this->assertSame(Participant::FINISH_NORMAL,
            $participant->getFinishStatus());
        $this->assertSame(516.67499999999995, $participant->getTotalTime());


        // Test any other participants to validate proper position
        $participant = $participants[8];
        $this->assertSame('SUCKER', $participant->getDriver()->getName());
    }

    /**
     * Test reading laps of participants
     *       Elapsed seconds based on session start and lap time?
     *
     * TODO: Double check gaps calculation. See gaps Race 1 @
     *       http://simresults.net/151107-1jd
     *       First driver should be second
     */
    public function testReadingLapsOfParticipants()
    {
        // Get participants
        $participants = $this->getWorkingReader()->getSession(5)
            ->getParticipants();


        // Get the laps of second participants (first is missing a lap)
        $participant = $participants[1];
        $laps = $participant->getLaps();

        // Validate we have 7 laps
        $this->assertSame(7, count($laps));

        // Get driver of first participant (only one cause there are no swaps)
        $driver = $participant->getDriver();

        // Get first lap only
        $lap = $laps[0];

        // Validate laps
        $this->assertSame(1, $lap->getNumber());
        $this->assertSame(3, $lap->getPosition());
        $this->assertSame(90.030, $lap->getTime());
        $this->assertSame(0, $lap->getElapsedSeconds());
        $this->assertSame($participant, $lap->getParticipant());
        $this->assertSame($driver, $lap->getDriver());
        $this->assertSame(0, $lap->getNumberOfCuts());

        // Get sector times
        $sectors = $lap->getSectorTimes();

        // Validate sectors
        $this->assertSame(36.008, $sectors[0]);
        $this->assertSame(22.301, $sectors[1]);
        $this->assertSame(31.7210, $sectors[2]);

        // Second lap
        $lap = $laps[1];
        $this->assertSame(2, $lap->getNumber());
        $this->assertSame(3, $lap->getPosition());
        $this->assertSame(84.2240, $lap->getTime());
        $this->assertSame(90.0300, $lap->getElapsedSeconds());
        $this->assertSame(3, $lap->getNumberOfCuts());
        $this->assertSame(2.872, $lap->getCutsTimeSkipped());
        $this->assertSame(3.023, $lap->getCutsTime());

        // Validate extra positions
        $laps = $participants[3]->getLaps();
        $this->assertSame(6, $laps[0]->getPosition());
        $this->assertSame(7, $laps[2]->getPosition());
    }

    /**
     * Test reading detailed cuts data
     */
    public function testCuts()
    {
        // Get participants
        $participants = $this->getWorkingReader()->getSession(5)
            ->getParticipants();


        // Get the laps of second participants (first is missing a lap)
        $participant = $participants[1];
        $laps = $participant->getLaps();

        // Second lap cuts
        $cuts = $laps[1]->getCuts();

        // Validate
        $this->assertSame(3, count($cuts));
        $this->assertSame(2.8780, $cuts[0]->getCutTime());
        $this->assertSame(2.7480, $cuts[0]->getTimeSkipped());
        $this->assertSame(1446150159, $cuts[0]->getDate()->getTimestamp());
        $this->assertSame(137, $cuts[0]->getElapsedSeconds());
        $this->assertSame($laps[1], $cuts[0]->getLap());
    }


    /**
     * Test reading incidents between cars
     */
    public function testIncidents()
    {
        // Get participants
        $incidents = $this->getWorkingReader()->getSession(5)
            ->getIncidents();

        // Validate first incident
        $this->assertSame(
            'Seb Solo reported contact with another vehicle '
           .'Trey. CollisionMagnitude: 1000',
            $incidents[0]->getMessage());
        $this->assertSame(1446150056,
            $incidents[0]->getDate()->getTimestamp());
        $this->assertSame(34, $incidents[0]->getElapsedSeconds());

        // Validate incident that would have a unknown participant. But now
        // it should not because we ignore these
        $this->assertSame(
            'JarZon reported contact with another vehicle '
           .'Trey. CollisionMagnitude: 327',
            $incidents[5]->getMessage());
        $this->assertSame(1446150147,
            $incidents[5]->getDate()->getTimestamp());
        $this->assertSame(125, $incidents[5]->getElapsedSeconds());
    }



    /**
     * Get a working reader
     */
    protected function getWorkingReader()
    {
        static $reader;

        // Reader aready created
        if ($reader)
        {
            return $reader;
        }

        // The path to the data source
        $file_path = realpath(__DIR__.'/logs/projectcars-server/sms_stats_data.json');

        // Get the data reader for the given data source
        $reader = Data_Reader::factory($file_path);

        // Return reader
        return $reader;
    }
}