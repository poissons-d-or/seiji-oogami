<?php
require('dbconnect.php');

//ログインユーザーのいいね数を取得
if (isset($_SESSION['id'])) {
  $likes = $db->prepare(
    'SELECT liked_post_id
     FROM likes
     WHERE member_id=?'
  );
  $likes->execute([$_SESSION['id']]);
  while ($likedPost = $likes->fetch()) {
    $myLikes[] = $likedPost['liked_post_id'];
  }
}
