<?php
session_start();
require('dbconnect.php');
require('getretweets.php');

if (isset($_SESSION['id'])) {
  // リツイートボタンクリック時の処理
  if (isset($_REQUEST['rt'])) {
    if (isset($myRts) && in_array($_REQUEST['rt'], $myRts, true)) {
      // いいね済みの投稿に対する処理
      $rtCancel = $db->prepare(
        'DELETE FROM display
         WHERE post_id=? AND display_member_id=? AND is_retweet=TRUE'
      );
      $rtCancel->execute([
        $_REQUEST['rt'],
        $_SESSION['id']
      ]);
    } else {
      // リツイートしていない投稿に対する処理
      $retweeted = $db->prepare(
        'INSERT INTO display
         SET post_id=?, display_member_id=?, is_retweet=TRUE, display_created=NOW();'
      );
      $retweeted->execute([
        $_REQUEST['rt'],
        $_SESSION['id']
      ]);
    }
  }
  // クリック処理後
  isset($_REQUEST['page']) ?
    //投稿一覧からの場合、もとの投稿一覧ページへ遷移
    header('Location: index.php?page=' . $_REQUEST['page'])
    :
    //個別投稿画面の場合、もとの投稿画面へ遷移
    header('Location: view.php?id=' . $_REQUEST['rt']);
  exit();
} else {
  //ログインしていない場合
  header('Location: index.php');
  exit();
}
