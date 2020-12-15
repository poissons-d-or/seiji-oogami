<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id'])) {
  $id = $_REQUEST['id'];

  // 投稿を検査する
  $messages = $db->prepare('SELECT * FROM posts WHERE id=?');
  $messages->execute([$id]);
  $message = $messages->fetch();

  if ($message['member_id'] === $_SESSION['id']) {
    // 投稿を削除する
    $del = $db->prepare(
      'DELETE FROM posts WHERE id=?;
       DELETE FROM display WHERE post_id=?;
       DELETE FROM likes WHERE liked_post_id=?;'
    );
    $del->execute([$id, $id, $id]);
  }
}
header('Location: index.php');
exit();
