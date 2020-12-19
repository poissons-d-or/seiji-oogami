<?php
require('dbconnect.php');

//ログインユーザーのリツイート数を取得
if (isset($_SESSION['id'])) {
  $retweets = $db->prepare(
    'SELECT post_id
     FROM display
     WHERE display_member_id=? AND is_retweet=TRUE'
  );
  $retweets->execute([$_SESSION['id']]);
  while ($rtPost = $retweets->fetch()) {
    $myRts[] = $rtPost['post_id'];
  }
}
