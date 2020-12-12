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

// 投稿を記録する
if (!empty($_POST)) {
  if ($_POST['message'] ?? '' !== '') {
    $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, created=NOW()');
    $message->execute([
      $member['id'],
      $_POST['message'],
      $_POST['reply_post_id']
    ]);

    header('Location: index.php');
    exit();
  } else {
    $message = '';
  }
} else {
  $message = '';
}

// ページングのためのパラメータ設定
$page = $_REQUEST['page'] ?? 1;
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$count = $counts->fetch();
$maxPage = ceil($count['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;

// 投稿を取得する（投稿内容、投稿者名、写真、投稿日時)
$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

// *****ここから課題で追加***** 
// ログインユーザーがいいねした投稿を取得する
$likes = $db->prepare('SELECT liked_post_id FROM likes WHERE member_id=?');
$likes->execute([$member['id']]);
while ($likedPost = $likes->fetch()) {
  $myLikes[] = $likedPost['liked_post_id'];
}
// ログインユーザーがリツイートした投稿を取得する
$retweets = $db->prepare('SELECT RTed_post_id FROM retweets WHERE member_id=?');
$retweets->execute([$member['id']]);
while ($RTedPost = $retweets->fetch()) {
  $myRTs[] = $RTedPost['RTed_post_id'];
}
// いいねボタンクリック時の処理
if (isset($_REQUEST['like'])) {
  if (isset($myLikes) && in_array($_REQUEST['like'], $myLikes)) { // いいね済みの投稿に対する処理
    $cancel = $db->prepare('DELETE FROM likes WHERE liked_post_id=? AND member_id=?');
    $cancel->execute([
      $_REQUEST['like'],
      $member['id']
    ]);
    // いいね取消し後、投稿一覧へ遷移する
    header('Location: index.php?page=' . $page);
    exit();
  } else { // いいねしていない投稿に対する処理
    $liked = $db->prepare('INSERT INTO likes SET liked_post_id=?, member_id=?, created_at=NOW()');
    $liked->execute([
      $_REQUEST['like'],
      $member['id']
    ]);
    // いいねの処理を実行し、投稿一覧へ遷移する
    header('Location: index.php?page=' . $page);
    exit();
  }
}

//リツイートボタンクリック時の処理

/* SQL文メモ
INSERT INTO retweets SET RTed_post_id=?, member_id=?, created_at=NOW()


header('Location: index.php');
exit();
*/
// *****ここまで課題で追加*****

// 返信の場合
if (isset($_REQUEST['res'])) {
  $response = $db->prepare('SELECT m.name, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=?');
  $response->execute([$_REQUEST['res']]);

  $table = $response->fetch();
  $message = '@' . $table['name'] . ' ' . $table['message'];
}

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
      <div style="text-align: right"><a href="logout.php">ログアウト</a></div>
      <form action="" method="post">
        <dl>
          <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
          <dd>
            <textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
            <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>">
          </dd>
        </dl>
        <div>
          <input type="submit" value="投稿する">
        </div>
      </form>

      <!-- 投稿取得 -->
      <?php foreach ($posts as $post) : ?>
        <div class="msg">
          <!-- *****名前、投稿、日時、返信ボタンの表示を改変***** -->
          <img src="member_picture/<?php echo h($post['picture']); ?>" width="60" alt="<?php echo h($post['name']); ?>" />
          <p>
            <span class="name"><?php echo h($post['name']); ?></span>
            <span class="day">
              <a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
              <?php if ($post['reply_post_id'] > 0) : ?>
                <a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">返信元のメッセージ</a>
              <?php endif; ?>
              <?php if ($_SESSION['id'] === $post['member_id']) : ?>
                [<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color: #F33;">削除</a>]
              <?php endif; ?>
            </span><!-- /.day -->
            <br>
            <?php echo makeLink(h($post['message'])); ?>
          </p>

          <!-- *****ここから課題で追加***** -->
          <?php
          // いいね数の取得
          $likeCounts = $db->prepare('SELECT COUNT(*) AS likeCNT FROM likes WHERE liked_post_id=?');
          $likeCounts->execute([$post['id']]);
          $likeCount = $likeCounts->fetch();
          // リツイート数の取得
          $rtCounts = $db->prepare('SELECT COUNT(*) AS rtCNT FROM retweets WHERE RTed_post_id=?');
          $rtCounts->execute([$post['id']]);
          $rtCount = $rtCounts->fetch();
          ?>
          <div class="icons">
            <a href="index.php?res=<?php echo h($post['id']); ?>"><img src="images/respond.png" alt=""></a>

            <a class="like-button" href="index.php?like=<?php echo h($post['id']); ?>&page=<?php echo h($page); ?>">
              <?php // ログイン中のユーザーがいいねした投稿のidをチェック
              $myLikeCnt = FALSE;
              if (isset($myLikes)) {
                foreach ($myLikes as $myLike) {
                  if ($myLike === $post['id']) {
                    $myLikeCnt = TRUE;
                  }
                }
              }
              ?>
              <?php if ($myLikeCnt) : ?>
                <!-- ログイン中のユーザーがいいねしている場合 -->
                <img src="images/liked.png" alt="">
              <?php else : ?>
                <!-- ログイン中のユーザーがいいねしていない場合 -->
                <img src="images/like.png" alt="">
              <?php endif; ?>
              <!-- いいねされた数を表示 -->
              <?php echo $likeCount['likeCNT']; ?>
            </a><!-- /.like-button -->

            <a class="retweet-button" href="index.php">
              <?php // ログイン中のユーザーがリツイートした投稿のidをチェック
              $myRTCnt = FALSE;
              if (isset($myRTs)) {
                foreach ($myRTs as $myRT) {
                  if ($myRT === $post['id']) {
                    $myRTCnt = TRUE;
                  }
                }
              }
              ?>
              <?php if ($myRTCnt) : ?>
                <!-- ログイン中のユーザーがリツイートしている場合 -->
                <img src="images/retweeted.png" alt="">
              <?php else : ?>
                <!-- ログイン中のユーザーがリツイートしていない場合 -->
                <img src="images/retweet.png" alt="">
              <?php endif; ?>
              <!-- リツイートされた数を表示 -->
              <?php echo $rtCount['rtCNT']; ?>
            </a><!-- /.retweet-button -->
          </div><!-- /.icons -->
          <!-- *****ここまで課題で追加**** -->

        </div><!-- /.msg -->
      <?php endforeach; ?>

      <ul class="paging">
        <?php if ($page > 1) : ?>
          <li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
        <?php else : ?>
          <li>前のページへ</li>
        <?php endif; ?>
        <?php if ($page < $maxPage) : ?>
          <li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
        <?php else : ?>
          <li>次のページへ</li>
        <?php endif; ?>
      </ul>
    </div><!-- /content -->
  </div><!-- /wrap -->
</body>

</html>
