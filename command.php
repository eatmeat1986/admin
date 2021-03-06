<?php
session_start();

require_once('battle/text.php');
require_once('battle/saveData.php');

// 戦闘テキストを管理するClass
use Battle\Text;
// データセーブを管理するClass
use Battle\SaveData;

class Command
{
    /**
     * 主人公と敵側のHP,MPの加算減算処理
     * 現在、主人公はCOOKIE側、敵側は SESSIONで保持して計算
     * PHP練習目的のため不統一だが、最終的に統一予定。
     *
     * POST形式、FORM送信後のデータ受け取りをトリガーに戦闘コメ* ントを配列にしgate_way.phpに渡す。その後jsonエンコード* 処理を経てbattle.phpでJSを用いてコメント内容を使用
     *
     * @param object $player
     * @param object $pinoko
     * @return array 敵味方コメントとHP
     */
    public function get($player, $pinoko)
    {

        //使用するスキルは乱数発生
        $player_use_skill_id = rand(2, count($player->skills)-1);
        $pinoko_use_skill_id = rand(0, count($pinoko->skills)-2);

        // Objectの更新とcokkieセット
        $player = $this->updateCharStatus('player', $player, $pinoko->skills, $pinoko_use_skill_id);
        $pinoko = $this->updateCharStatus('pinoko', $pinoko, $player->skills, $player_use_skill_id);

        $player_death = $this->deathCheck($player->skill[$player_use_skill_id]);
        $pinoko_death = $this->deathCheck($pinoko->skill[$pinoko_use_skill_id]);


        // attackがない場合は初回である
        $is_first = !isset($_REQUEST["attack"]) ? true: false;
        // 戦闘表示テキストの取得
        $strike_text = $this->getStrikeTexts($player, $pinoko, $player_use_skill_id, $pinoko_use_skill_id, $is_first);

        $player_status = array(
            "name" => $player->name,
            "hp" => $player->hp,
            "mp"=>$player->mp,
            "death"=>false,
        );
        $pinoko_status = array(
            "name" => $pinoko->name,
            "hp" => $pinoko->hp,
            "mp"=>$pinoko->mp,
            "death"=>false,
        );

        //取得した戦闘コメント、更新された各オブジェクトのHPプロパティを配列化して返す。呼び出しはgate_way.phpより行う。
        return array(
            "strike_text" => $strike_text['player'],
            "enemy_strike_text" => $strike_text['enemy'],
            "pinoko" => $pinoko_status,
            "player" => $player_status,
            //"player_hp" => "HP:".$player->hp,
            "array_detail" => null // TODO: 使われて無さそう
        );
    }

    /**
     * キャラクター情報の更新を行う
     * @param String $char_name    キャラクター名(player, pinokoなどのほう外部入力されたものではない)
     * @param Object $char         キャラクターClass Object
     * @param Array  $skills       使用されるスキル群
     * @param Int    $use_skill_id 使用されるスキルID
     * @return Object 更新したキャラクター
     */
    private function updateCharStatus(String $char_name, $char, array $skills, Int $use_skill_id)
    {
        // SaveDataクラスは毎回インスタンス化されるけど一旦これで
        $save = new Battle\SaveData();
        //COOKIE,SESSIONいずれも挿入する情報は必要最低限にする。HP,MP意外戦闘で不必要な情報は不要。
        if (!isset($_COOKIE[$char_name. '_hp'])) {
            // hpがない場合生成
            $save->cookie($char_name. '_hp', $char->hp);
        } else {
            //すでにHPがある場合は戦闘の減産処理を行う
            $char->hp = $_COOKIE[$char_name. '_hp'];
            $damage = $skills[$use_skill_id]["damage"];
            $hp = $char->hp - $damage;
            $char->setHp($hp);
            $save->cookie($char_name. '_hp', $hp);
        }
        // 更新済みObjectを返す
        return $char;
    }

    /**
     * 戦闘テキストをまとめて取得するロジック
     *
     * @param Object $player Player class
     * @param Object $enemy Enemy class
     * @return Array playerとenemyの戦闘テキスト
     */
    private function getStrikeTexts($player, $enemy, $player_use_skill_id, $enemy_use_skill_id, $is_first)
    {
        $textGenerator = new Battle\Text();

        $player_text = '';
        $enemy_text = '';

        // 初回ターンは敵との遭遇テキスト
        if ($is_first) {
            $player_text = "$enemy->name がおそいかかってきた!!!";
        } else {
            $player_text = $textGenerator->attackText($player->name, $player->skills[$player_use_skill_id]['text'], $enemy->name, $player->skills[$player_use_skill_id]['damage']);
            $enemy_text = $textGenerator->attackText($enemy->name, $enemy->skills[$enemy_use_skill_id]['text'], $player->name, $enemy->skills[$enemy_use_skill_id]['damage']);
        }

        return array(
            'player' => $player_text,
            'enemy' => $enemy_text,
        );
    }

    public function deathCheck($charskill)
    {
        return $charskill["death"];
    }
}
