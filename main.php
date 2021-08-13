<?php

/**
 * people hanging out in groups 3-5
 * jnoehren@apartmentlist.com
 * In current state, it doesn't work properly. There's some design issues with findCandidates().
 * it really needs to know who has (or hasn't) been assigned yet in order to properly choose a
 * good candidate
 */
$teamMgr = TeamMgr::MakeRandomTeams(3);


$candidates = getCandidates($teamMgr);
print_r(json_encode($candidates));
printf("\n");

/**
 * @param TeamMgr $teamMgr
 * @return array
 */
function getCandidates(TeamMgr $teamMgr)
{
    $minGroupSize = 3;
    $maxGroupSize = 5;

    $candidates = $teamMgr->getEmployees();

    $groups = [];

    $numInCompany = count($candidates);
    for ($i = 0; $i < $numInCompany; $i++) {
        $group = [];
        for ($j = 0; $j < $maxGroupSize; $j++) {
            // $candidate = array_pop($candidates);
            $candidate = $teamMgr->findCandidate($group);
            if (empty($candidate)) {
                break;
            }
            $group[] = $candidate;
        }
        $groups[] = $group;
    }

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

class TeamMgr
{
    public static function MakeRandomTeams($numTeams)
    {
        $numInTeam = 5;
        $candidateIdx = 1;

        $teams = [];
        foreach (range(1, $numTeams) as $teamNum) {
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

    public function findCandidate($currentGroup)
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
    protected $members;

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