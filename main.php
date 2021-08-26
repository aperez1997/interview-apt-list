<?php

/**
 * people hanging out in groups 3-5
 * jnoehren@apartmentlist.com
 * In current state, it doesn't work properly. There's some design issues with findCandidates().
 * it really needs to know who has (or hasn't) been assigned yet in order to properly choose a
 * good candidate
 */

const MIN_SIZE = 3;
const MAX_SIZE = 5;


$rv = testCandidateAlgo();
printf("Result %b\n", $rv);

$teamMgr = TeamMgr::MakeRandomTeams(3);


/**
printf("employees:\n");
print_r(json_encode($teamMgr->getEmployees()));
printf("\ncandidates:\n");
$candidates = getCandidates($teamMgr);
print_r(json_encode($candidates));
printf("\n");
 */

function testCandidateAlgo()
{
    $result = true;
    for ($i = 2; $i < 5; $i++){
        $teamMgr = TeamMgr::MakeRandomTeams($i);
        $candidates = getCandidates($teamMgr);
        $employeeCount = $teamMgr->getEmployees();
        printf("Team %s => Candidates %s\n", json_encode($teamMgr->getTeams()), json_encode($candidates));
        $rv = testGroupComp($candidates, count($employeeCount));
        if (!$rv) {
            printf("Group [%s] failed\n", json_encode($candidates));
        }
        $result &= $rv;
    }

    $result &= testRepeat();

    return $result;
}

function testRepeat()
{
    $teamMgr = TeamMgr::MakeRandomTeams(3);
    $candidates1 = getCandidates($teamMgr);
    $candidates2 = getCandidates($teamMgr);

    if ($candidates1 == $candidates2){
        printf("Got the same result! not random\n");
        return false;
    }
    return true;
}

function testGroupComp($candidates, $expectedTotal)
{
    $foundCount = 0;
    $foundEmployees = [];
    foreach ($candidates as $group){
        $count = count($group);
        if ($count < MIN_SIZE || $count > MAX_SIZE){
            printf("Error: group size is {$count} for %s\n", json_encode($group));
            return false;
        }
        foreach ($group as $employee){
            if (array_key_exists($employee, $foundEmployees)){
                printf("Employee %s has appeared more than once\n", $employee);
                return false;
            }
            $foundEmployees[$employee] = 1;
        }

        $foundCount += $count;
    }
    if ($foundCount != $expectedTotal){
        printf("Wanted count %s got %s\n", $expectedTotal, $foundCount);
        return false;
    }
    return true;
}

/**
 * @param TeamMgr $teamMgr
 * @return array
 */
function getCandidates(TeamMgr $teamMgr)
{
    $minGroupSize = MIN_SIZE;
    $maxGroupSize = MAX_SIZE;

    $candidates = $teamMgr->getEmployees();

    $groups = [];

    // list($i, $candidates, $groups) = getGroupsDefault($candidates, $maxGroupSize, $groups);
    $groups = getGroupsSmarter($teamMgr);

// printf("Check for min group size\n");
    $lastGroupIndex = count($groups) - 1;
    $lastGroupSize = count($groups[$lastGroupIndex]);
    if ($lastGroupSize < $minGroupSize) {
        // printf("We need to fix groups\n");
        $numMissing = $minGroupSize - $lastGroupSize;
        //$firstGroup = ;
        for ($i = 0; $i < $numMissing; $i++) {
            $movingCandidate = array_pop($groups[0]);
            $groups[$lastGroupIndex][] = $movingCandidate;
        }
    }
    return $groups;
}

/**
 * @param array $candidates
 * @param $maxGroupSize
 * @param array $groups
 * @return array
 */
function getGroupsDefault(array $candidates, $maxGroupSize, array $groups)
{
    shuffle($candidates);
    $numInCompany = count($candidates);
    for ($i = 0; $i < $numInCompany; $i++) {
        $group = [];
        for ($j = 0; $j < $maxGroupSize; $j++) {
            $candidate = array_pop($candidates);
            // $candidate = $teamMgr->findCandidate($group);
            if (empty($candidate)) {
                break;
            }
            $group[] = $candidate;
        }
        if (!empty($group)){
            $groups[] = $group;
        }
    }
    return array($i, $candidates, $groups);
}

function getGroupsSmarter(TeamMgr $teamMgr)
{
    $groups = [];
    $candidates = $teamMgr->getEmployees();
    shuffle($candidates);
    $numInCompany = count($candidates);
    for ($i = 0; $i < $numInCompany; $i++) {
        $group = [];
        for ($j = 0; $j < MAX_SIZE; $j++) {
            $candidate = $teamMgr->findCandidate($group, $candidates);
            if (empty($candidate)) {
                break;
            }
            $removeIdx = array_search($candidate, $candidates);
            unset($candidates[$removeIdx]);
            $group[] = $candidate;
        }
        if (!empty($group)){
            $groups[] = $group;
        }
    }
    return $groups;
}


class TeamMgr
{
    public static function MakeRandomTeams($numTeams)
    {
        $candidateIdx = 1;

        $teams = [];
        foreach (range(1, $numTeams) as $teamNum) {
            $numInTeam = rand(3,6);
            $teamIds = range($candidateIdx, $candidateIdx + $numInTeam - 1);

            $team = new Team($teamIds);
            $candidateIdx += $numInTeam;
            $teams[] = $team;
        }

        $teamMgr = new TeamMgr($teams);
        return $teamMgr;
    }

    /** @var Team[] */
    protected $teams;

    /**
     * TeamMgr constructor.
     * @param Team[] $teams
     */
    public function __construct(array $teams)
    {
        $this->teams = $teams;
    }

    /**
     * @return Team[]
     */
    public function getTeams()
    {
        return $this->teams;
    }

    public function getEmployees()
    {
        $employees = [];
        foreach ($this->teams as $team){
             $employees = array_merge($employees, $team->getMembers());
        }
        return $employees;
    }

    public function getTeamForEmployee($employee){
        foreach ($this->teams as $team) {
            if ($team->isOnTeam($employee)){
                return $team;
            }
        }
        return null;
    }

    public function findCandidate($currentGroup, $remainingCandidate)
    {
        // look at all remaining candidates
        foreach ($remainingCandidate as $candidate){
            // see if any of them are in the same team as a current group member
            $hasTeamMemberAlready = false;
            foreach ($currentGroup as $currentMember){
                $team = $this->getTeamForEmployee($currentMember);

                if ($team->isOnTeam($candidate)){
                    $hasTeamMemberAlready = true;
                    break;
                }
            }

            if (!$hasTeamMemberAlready){
                return $candidate;
            }
        }

        // what if we didnt find someone: return the first remaining candidate
        return reset($remainingCandidate);
    }

    public function findCandidate_old($currentGroup)
    {
        // look at all teams
        foreach ($this->teams as $team){
            // look at all current group
            $found = false;
            foreach ($currentGroup as $currentMember){
                if ($team->isOnTeam($currentMember)){
                    $found = true;
                }
            }

            // if no-one in current group is in the current team, return someone
            if (!$found){
                $members = $team->getMembers();
                break;
            }
        }

        // at the end, if we didn't find anyone, pick someone randomly
        if (empty($members)){
            $members = $this->getRandomTeam()->getMembers();
        }
        return reset($members);
    }

    public function getRandomTeam()
    {
        return $this->teams[0];
    }
}

class Team
{
    /** @var int[] */
    public $members;

    /**
     * Team constructor.
     * @param int[] $members
     */
    public function __construct(array $members)
    {
        $this->members = $members;
    }

    /**
     * @return int[]
     */
    public function getMembers()
    {
        return $this->members;
    }

    public function isOnTeam($employee)
    {
        return in_array($employee, $this->members);
    }
}