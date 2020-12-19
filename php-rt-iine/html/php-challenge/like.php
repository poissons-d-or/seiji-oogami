<?php
session_start();
require('dbconnect.php');
require('getlikes.php');

if (isset($_SESSION['id'])) {
  // いいねボタンクリック時の処理
  if (isset($_REQUEST['like'])) {
    if (isset($myLikes) && in_array($_REQUEST['like'], $myLikes, true)) {
      // いいね済みの投稿に対する処理
      $cancel = $db->prepare(
        'DELETE FROM likes
         WHERE liked_post_id=? AND member_id=?'
      );
      $cancel->execute([
        $_REQUEST['like'],
        $_SESSION['id']
      ]);
    } else {
      // いいねしていない投稿に対する処理
      $liked = $db->prepare(
        'INSERT INTO likes
         SET liked_post_id=?, member_id=?, created_at=NOW()'
      );
      $liked->execute([
        $_REQUEST['like'],
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
    header('Location: view.php?id=' . $_REQUEST['like']);
  exit();
} else {
  //ログインしていない場合
  header('Location: index.php');
  exit();
}
