<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
  // ログインしている
  $_SESSION['time'] = time();

  $members = $db->prepare('SELECT * FROM members WHERE id=?');
  $members->execute([$_SESSION['id']]);
  $member = $members->fetch();
} else {
  // ログインしていない
  header('Location: login.php');
  exit();
}

if (empty($_REQUEST['id'])) {
  header('Location: index.php');
  exit();
}

// 投稿を取得する *****課題で改変*****
// displayテーブルとリレーションを張る
$posts = $db->prepare(
  'SELECT p.*, m.name, m.picture, d.* 
   FROM members m, posts p
   LEFT JOIN display d ON p.id=d.post_id
   WHERE m.id=p.member_id AND d.post_id=? 
   ORDER BY d.display_created DESC'
);
$posts->execute([$_REQUEST['id']]);

// *****ここから課題で追加***** 
// ログインユーザーがいいねした投稿を取得する
$likes = $db->prepare(
  'SELECT liked_post_id
   FROM likes
   WHERE member_id=?'
);
$likes->execute([$member['id']]);
while ($likedPost = $likes->fetch()) {
  $myLikes[] = $likedPost['liked_post_id'];
}
// ログインユーザーがリツイートした投稿を取得する
$retweets = $db->prepare(
  'SELECT post_id
   FROM display
   WHERE display_member_id=? AND is_retweet=TRUE'
);
$retweets->execute([$member['id']]);
while ($RTedPost = $retweets->fetch()) {
  $myRTs[] = $RTedPost['post_id'];
}
// いいねボタンクリック時の処理
if (isset($_REQUEST['like'])) {
  if (isset($myLikes) && in_array($_REQUEST['like'], $myLikes)) { // いいね済みの投稿に対する処理
    $cancel = $db->prepare(
      'DELETE FROM likes
       WHERE liked_post_id=? AND member_id=?'
    );
    $cancel->execute([
      $_REQUEST['like'],
      $member['id']
    ]);
    // いいね取消し後、投稿画面へ戻る
    header('Location: view.php?id=' . $_REQUEST['like']);
    exit();
  } else { // いいねしていない投稿に対する処理
    $liked = $db->prepare(
      'INSERT INTO likes
       SET liked_post_id=?, member_id=?, created_at=NOW()'
    );
    $liked->execute([
      $_REQUEST['like'],
      $member['id']
    ]);
    // いいねの処理を実行し、投稿画面へ戻る
    header('Location: view.php?id=' . $_REQUEST['like']);
    exit();
  }
}
//リツイートボタンクリック時の処理
if (isset($_REQUEST['rt'])) {
  if (isset($myRTs) && in_array($_REQUEST['rt'], $myRTs)) {
    // リツイート済の投稿に対する処理
    $rtCancel = $db->prepare(
      'DELETE FROM display
       WHERE post_id=? AND display_member_id=? AND is_retweet=TRUE'
    );
    $rtCancel->execute([
      $_REQUEST['rt'],
      $member['id']
    ]);
    // リツイート取消し後、投稿画面へ戻る
    header('Location: view.php?id=' . $_REQUEST['rt']);
    exit();
  } else {
    // リツイートしていない投稿に対する処理
    $retweeted = $db->prepare(
      'INSERT INTO display
       SET post_id=?, display_member_id=?, is_retweet=TRUE, display_created=NOW();'
    );
    $retweeted->execute([
      $_REQUEST['rt'],
      $member['id']
    ]);
    // リツイートの処理を実行し、投稿画面へ戻る
    header('Location: view.php?id=' . $_REQUEST['rt']);
    exit();
  }
}
// *****ここまで課題で追加*****

// htmlspecialcharsのショートカット
function h($value)
{
  return htmlspecialchars($value, ENT_QUOTES);
}

// 本文内のURLにリンクを設定する
function makeLink($value)
{
  return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>', $value);
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>ひとこと掲示板</title>

  <link rel="stylesheet" href="style.css" />
</head>

<body>
  <div id="wrap">
    <div id="head">
      <h1>ひとこと掲示板</h1>
    </div>
    <div id="content">
      <p>&laquo;<a href="index.php">一覧にもどる</a></p>
      <?php if ($post = $posts->fetch()) : ?>
        <div class="msg">
          <!-- *****名前、投稿、日時、返信ボタンの表示を改変***** -->
          <img src="member_picture/<?php echo h($post['picture']); ?>" width="60" alt="<?php echo h($post['name']); ?>" />
          <p>
            <span class="name"><?php echo h($post['name']); ?></span>
            <span class="day">
              <?php echo h($post['created']); ?>
              <?php if ($post['reply_post_id'] > 0) : ?>
                <a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">返信元のメッセージ</a>
              <?php endif; ?>
              <?php if ($_SESSION['id'] === $post['member_id']) : ?>
                [<a href="delete.php?id=<?php echo h($post['post_id']); ?>" style="color: #F33;">削除</a>]
              <?php endif; ?>
            </span><!-- /.day -->
            <br>
            <?php echo makeLink(h($post['message'])); ?>
          </p>

          <!-- *****ここから課題で追加***** -->
          <?php
          // いいね数の取得
          $likeCounts = $db->prepare(
            'SELECT COUNT(*) AS likeCNT
             FROM likes
             WHERE liked_post_id=?'
          );
          $likeCounts->execute([$post['post_id']]);
          $likeCount = $likeCounts->fetch();
          // リツイート数の取得
          $rtCounts = $db->prepare(
            'SELECT COUNT(*) AS rtCNT
             FROM display
             WHERE post_id=? AND is_retweet=TRUE'
          );
          $rtCounts->execute([$post['post_id']]);
          $rtCount = $rtCounts->fetch();
          ?>
          <div class="icons">
            <a href="index.php?res=<?php echo h($post['post_id']); ?>"><img src="images/respond.png" alt="返信する"></a>

            <a class="like-button" href="view.php?id=<?php echo h($post['post_id']); ?>&like=<?php echo h($post['post_id']); ?>">
              <?php // ログイン中のユーザーがいいねした投稿のidをチェック
              if (isset($myLikes)) {
                if (in_array($post['post_id'], $myLikes)) {
              ?>
                  <!--  ログイン中のユーザーがいいねしている場合 -->
                  <img src="images/liked.png" alt="いいねを取消す">
                <?php
                } else {
                ?>
                  <!--  ログイン中のユーザーがいいねしていない場合 -->
                  <img src="images/like.png" alt="いいねする">
              <?php
                }
              }
              ?>
              <!-- いいねされた数を表示 -->
              <?php echo $likeCount['likeCNT']; ?>
            </a><!-- /.like-button -->

            <a class="retweet-button" href="view.php?id=<?php echo h($post['post_id']); ?>&rt=<?php echo h($post['post_id']); ?>">
              <?php // ログイン中のユーザーがリツイートした投稿のidをチェック
              if (isset($myRTs)) {
                if (in_array($post['post_id'], $myRTs)) {
              ?>
                  <!-- ログイン中のユーザーがリツイートしている場合 -->
                  <img src="images/retweeted.png" alt="リツイートを取消す">
                <?php
                } else {
                ?>
                  <!-- ログイン中のユーザーがリツイートしていない場合 -->
                  <img src="images/retweet.png" alt="リツイートする">
              <?php
                }
              }
              ?>
              <!-- リツイートされた数を表示 -->
              <?php echo $rtCount['rtCNT']; ?>
            </a><!-- /.retweet-button -->
          </div><!-- /.icons -->
          <!-- *****ここまで課題で追加**** -->
        </div>
      <?php else : ?>
        <p>その投稿は削除されたか、URLが間違っています</p>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>
