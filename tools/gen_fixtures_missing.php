<?php
$cap = __DIR__ . '/capture';
$out = dirname(__DIR__) . '/server/data';
@mkdir($out, 0777, true);

$missing = explode("\n", trim(<<<'TXT'
abortQuest
activateSeason
addFriend
assignEventQuest
assignTreasureEvent
buildHideoutRoom
buyDuelStamina
buyMultitaskingBooster
buyTrainingEnergy
checkHideoutRoomActivityFinished
claimFreeTreasureRevealItems
claimMissedLeagueFightsRewards
claimSeasonReward
claimTreasureEventReward
collectHideoutFightRewards
collectHideoutRoomActivityResult
collectTreasureCellReward
createCharacter
createNextTreasureEventLevel
createResourceRequest
donateToGuild
finishHideoutTutorial
finishTraining
finishVideoAdvertisment
getCharacterMaxSpendableAmount
getGuildBattleHistoryFight
getGuildBattleHistoryFights
getGuildList
getHideoutBattleHistoryFights
getHideoutOpponent
getMissedDuelsNew
getMissedLeagueFights
initVideoAdvertisment
instantFinishHideoutRoomActivity
joinGuild
markPrivateSystemMessageAsRead
moveInventoryItem
openOpticalChangeChests
openSeasonPanel
openSeasonPass
openTreasureCell
registerUserSSO
retrieveGuildCompetitionTournamentLeaderboardGuildsAroundOwnGuild
setCharacterName
startHideoutRoomProduction
startHideoutTutorialFight
startNewStoryDungeonStep
storeHideoutRoom
syncFriendBar
updateSeasonReward
upgradeHideoutRoom
TXT
));

$best = []; // action => ['size'=>, 'data'=>]
foreach (glob("$cap/*.json") as $f) {
    $j = json_decode(file_get_contents($f), true);
    $a = $j['action'] ?? '';
    if (!in_array($a, $missing, true)) continue;
    if (($j['response']['error'] ?? '') !== '') continue;
    $data = $j['response']['data'] ?? null;
    if (!is_array($data) || count($data) === 0) continue;
    $sz = strlen(json_encode($data));
    if (!isset($best[$a]) || $sz > $best[$a]['size']) {
        $best[$a] = ['size' => $sz, 'data' => $data];
    }
}
$written = [];
foreach ($best as $a => $b) {
    file_put_contents("$out/$a.json", json_encode($b['data'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $written[] = $a;
}
sort($written);
foreach ($written as $a) echo "OK  $a\n";
$notFound = array_diff($missing, $written);
foreach ($notFound as $a) echo "MISS $a  (sem resposta de sucesso no HAR)\n";
