<?php
/**
 * 旅館詳細ページ(改修 Phase 3: 「知る → 見る → 行く → 決める → 回遊」の流れに再構成)。
 *
 * @var array  $hotel        正規化済み $hotel['xxx']
 * @var array  $rails        おすすめレール群(RailBuilderで重複除去済み)
 * @var array  $seenHotelNos ページ内表示済みhotelNo(JSレールのexclude用)
 * @var array  $highlights   おすすめポイント(県内相対評価・3-2)
 * @var array  $faq          よくある質問(3-5)
 * @var ?array $onsenNote    泉質の一般解説(3-3)
 * @var array  $tagChips     タグチップ(内部リンク)
 * @var string $reviewComment 軸別評価からの自動生成コメント(表示は1箇所のみ)
 * @var array  $xsell        ['rentacar_url','gasshuku_url']
 *
 * セクション構成:
 *  1. Hero(CTA込み)
 *  2. パンくず + タグチップ
 *  3. この宿について(hotelSpecial + おすすめポイント)
 *  4. 評価とクチコミ(レーダー + サマリー + 代表クチコミ/自動コメント を統合)
 *  5. 写真(フォトギャラリー + 客室)
 *  6. 温泉・お風呂(泉質・風呂タイプ + 泉質辞書)
 *  7. 館内・基本情報(IN/OUT/駐車場を吸収) + よくある質問
 *  8. アクセスと周辺(地図 + 最寄り駅 + 周辺スポットを統合)
 *  9. 空室確認CTA
 * 10. おすすめレール(近くの宿 / 同じ県 / タグ)
 * 11. 最近見た宿
 * 12. 旅をもっと楽しむ(xsell)
 */
$hero = !empty($hotel['hotelImageUrls']) ? $hotel['hotelImageUrls'] : [];
$ctaUrl = ($hotel['affiliateUrl'] ?? '') !== '' ? $hotel['affiliateUrl'] : '#';
$hasReview = ($hotel['reviewCount'] ?? 0) > 0;
$hasAxis = !empty($hotel['axis']);
?>

<div class="hero">
  <div class="topnav">
    <button class="icon-btn" aria-label="戻る" onclick="history.back()">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
    </button>
    <?php component('logo', ['variant' => 'mark', 'size' => 30]); ?>
    <button class="icon-btn" id="shareBtn" aria-label="共有する">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.7 10.7L15.3 7M8.7 13.3l6.6 3.7M6 14a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM18 8a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM18 21a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/></svg>
    </button>
  </div>

  <div class="hero-scroller" id="heroScroller">
    <?php if ($hero): ?>
      <?php foreach ($hero as $i => $src): ?>
        <div class="hero-slide"><img src="<?= e($src) ?>" alt="<?= e($hotel['hotelName']) ?>"<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>></div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="hero-slide"><div class="thumb-empty" style="width:100%;height:100%">No Image</div></div>
    <?php endif; ?>
  </div>
  <div class="hero-gradient"></div>

  <?php if (count($hero) > 1): ?>
    <div class="dots" id="dots">
      <?php foreach ($hero as $i => $_): ?>
        <span class="dot <?= $i === 0 ? 'active' : '' ?>"></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <button class="icon-btn fav hero-fav" id="favBtn"
          aria-label="お気に入りに追加" aria-pressed="false"
          data-fav-toggle
          data-hotel-no="<?= e($hotel['hotelNo']) ?>"
          data-hotel-name="<?= e($hotel['hotelName']) ?>"
          data-hotel-image="<?= e($hero[0] ?? '') ?>"
          data-hotel-area="<?= e($hotel['area']) ?>"
          data-hotel-rating="<?= e((string) $hotel['reviewAverage']) ?>"
          data-hotel-tags="<?= e(implode(',', (array) ($hotel['tags'] ?? []))) ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.5-4.6-10-9.2C.4 8.6 2 5 5.6 5c2 0 3.4 1.1 4.4 2.6C11 6.1 12.4 5 14.4 5 18 5 19.6 8.6 22 11.8 19.5 16.4 12 21 12 21z"/></svg>
  </button>

  <div class="hero-content">
    <h1 class="hotel-name serif"><?= e($hotel['hotelName']) ?></h1>
    <div class="hotel-sub">
      <?php if ($hotel['address']): ?><span><?= e($hotel['address']) ?></span><?php endif; ?>
      <?php if ($hasReview): ?>
        <span class="dot2">·</span>
        <span class="rating-badge">★ <?= e(number_format($hotel['reviewAverage'], 1)) ?> (<?= e((string) $hotel['reviewCount']) ?>)</span>
      <?php endif; ?>
    </div>
    <?php if ($hotel['catchCopy']): ?>
      <p class="catch serif"><?= e($hotel['catchCopy']) ?></p>
    <?php endif; ?>
    <?php if (!empty($hotel['badges'])): component('badge', ['badges' => $hotel['badges'], 'variant' => 'hero']); endif; ?>
    <?php // P0-1: ファーストビュー内の予約CTA ?>
    <a class="hero-book-cta" href="<?= e($ctaUrl) ?>" target="_blank" rel="sponsored nofollow noopener"
       data-track="detail_hero_cta" data-hotel="<?= e($hotel['hotelNo']) ?>">
      楽天トラベルで空室・料金を見る
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>
    </a>
  </div>
  <span class="rakuten-credit">楽天トラベル</span>
</div>

<div class="wrap">

  <?php // === 2. パンくず + タグチップ === ?>
  <?php if (!empty($breadcrumb)): ?>
    <nav class="breadcrumb" aria-label="パンくず">
      <?php foreach ($breadcrumb as $i => $bc): ?>
        <?php if (!empty($bc['href'])): ?><a href="<?= e($bc['href']) ?>"><?= e($bc['label']) ?></a><?php else: ?><span><?= e($bc['label']) ?></span><?php endif; ?>
        <?php if ($i < count($breadcrumb) - 1): ?><span class="sep">›</span><?php endif; ?>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php if (!empty($tagChips)): ?>
    <div class="tag-chips" aria-label="この宿のタグ">
      <?php foreach ($tagChips as $chip): ?>
        <?php if (!empty($chip['href'])): ?>
          <a class="tag-chip" href="<?= e($chip['href']) ?>" data-track="tag_chip" data-category="<?= e($chip['key']) ?>">#<?= e($chip['label']) ?></a>
        <?php else: ?>
          <span class="tag-chip">#<?= e($chip['label']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php // === 3. この宿について(紹介文 + おすすめポイント) === ?>
  <?php if ($hotel['hotelSpecial'] || !empty($highlights)): ?>
    <section class="block reveal">
      <h2 class="section-title"><span class="bar"></span>この宿について</h2>
      <?php if ($hotel['hotelSpecial'] && $hotel['hotelSpecial'] !== ($hotel['catchCopy'] ?? '')): ?>
        <p class="detail-special"><?= e($hotel['hotelSpecial']) ?></p>
      <?php endif; ?>
      <?php if (!empty($highlights)): ?>
        <ul class="highlight-list">
          <?php foreach ($highlights as $h): ?>
            <li class="highlight-item">
              <span class="highlight-ico" aria-hidden="true">
                <?php if ($h['icon'] === 'star'): ?>
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8z"/></svg>
                <?php elseif ($h['icon'] === 'chat'): ?>
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a8 8 0 01-8 8H4l2-3a8 8 0 1115-5z"/></svg>
                <?php elseif ($h['icon'] === 'yen'): ?>
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3l6 8 6-8M12 11v10M7 14h10M7 18h10"/></svg>
                <?php else: ?>
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 12c0-3 2-4 2-6S7 3 7 3m5 9c0-3 2-4 2-6s-2-3-2-3M3 16h18v2a4 4 0 01-4 4H7a4 4 0 01-4-4v-2z"/></svg>
                <?php endif; ?>
              </span>
              <span class="highlight-text"><?= e($h['text']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="highlight-note">※ 当サイトが収集した楽天トラベルの評価データにもとづく比較です。</p>
      <?php endif; ?>
      <?php if (!empty($audiences)): ?>
        <h3 class="section-subtitle">こんな人におすすめ</h3>
        <div class="audience-chips">
          <?php foreach ($audiences as $a): ?>
            <span class="audience-chip"><?= e($a) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php // === 3.5 この宿のこだわり(公式サイトより・content/official にあれば) === ?>
  <?php if (!empty($officialSummary)): ?>
    <section class="block reveal">
      <h2 class="section-title"><span class="bar"></span>この宿のこだわり</h2>
      <?php if (!empty($officialSummary['catch'])): ?>
        <p class="official-catch serif"><?= e($officialSummary['catch']) ?></p>
      <?php endif; ?>
      <div class="official-points">
        <?php foreach ((array) $officialSummary['points'] as $pt): ?>
          <div class="official-point">
            <h3 class="official-point-title"><?= e($pt['title']) ?></h3>
            <p><?= e($pt['body']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="official-note">※ <a href="<?= e($officialSummary['sourceUrl']) ?>" target="_blank" rel="nofollow noopener">公式サイト</a>の情報をもとに構成(<?= e(date('Y年n月', strtotime((string) $officialSummary['fetchedAt']))) ?>時点)。最新情報は公式サイトをご確認ください。</p>
    </section>
  <?php endif; ?>

  <?php // === 4. 評価とクチコミ(レーダー + サマリー + 代表1件/自動コメント を統合) === ?>
  <?php if ($hasAxis || $hasReview): ?>
    <section class="block reveal">
      <h2 class="section-title"><span class="bar"></span>評価とクチコミ</h2>

      <?php if ($hasReview): ?>
        <div class="review-summary">
          <div class="avg"><?= e(number_format($hotel['reviewAverage'], 1)) ?></div>
          <div>
            <div class="stars" aria-hidden="true"><?= str_repeat('★', (int) round($hotel['reviewAverage'])) ?><span class="muted"><?= str_repeat('★', 5 - (int) round($hotel['reviewAverage'])) ?></span></div>
            <div class="cnt"><?= e((string) $hotel['reviewCount']) ?>件の評価</div>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($axisCompare)): ?>
        <?php // 増強1: 軸別評価 × 県平均。図(レーダー)に加えテキスト化してSEO・比較性を確保 ?>
        <div class="axis-compare">
          <?php foreach ($axisCompare as $ac): ?>
            <div class="axis-row">
              <span class="axis-label"><?= e($ac['label']) ?></span>
              <span class="axis-bar" aria-hidden="true"><span class="axis-fill" style="width:<?= e((string) min(100, $ac['value'] / 5 * 100)) ?>%"></span><span class="axis-avg-mark" style="left:<?= e((string) min(100, $ac['avg'] / 5 * 100)) ?>%"></span></span>
              <span class="axis-nums"><strong><?= e(number_format($ac['value'], 1)) ?></strong><span class="axis-avg">県平均 <?= e(number_format($ac['avg'], 1)) ?><?= $ac['diff'] > 0 ? '(+' . e(number_format($ac['diff'], 1)) . ')' : '' ?></span></span>
            </div>
          <?php endforeach; ?>
          <p class="axis-note">県平均は<?= e($hotel['area']) ?>の掲載旅館(クチコミあり)の平均値。縦線が県平均の位置。</p>
        </div>
      <?php endif; ?>
      <?php // レーダーチャートは軸別比較バー(axis-compare)と重複するため廃止 ?>

      <?php if (!empty($prosCons['pros']) || !empty($prosCons['cons'])): ?>
        <div class="proscons">
          <?php foreach ((array) $prosCons['pros'] as $pc): ?>
            <p class="pros-item"><span class="pc-mark" aria-hidden="true">◎</span><?= e($pc) ?></p>
          <?php endforeach; ?>
          <?php foreach ((array) $prosCons['cons'] as $pc): ?>
            <p class="cons-item"><span class="pc-mark" aria-hidden="true">△</span><?= e($pc) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php // 代表クチコミ(実データ)があれば最優先。無ければ自動生成コメントを1回だけ表示。 ?>
      <?php if (!empty($hotel['latestReview'])): $r = $hotel['latestReview']; ?>
        <h3 class="section-subtitle">お客様の声(抜粋・1件)</h3>
        <div class="review-card">
          <p class="comment"><?= e($r['comment']) ?></p>
          <div class="review-meta">
            <?php if (!empty($r['score'])): ?><span class="review-score">★ <?= e(number_format((float) $r['score'], 1)) ?></span><?php endif; ?>
            <span><?= e($r['meta']) ?></span>
          </div>
        </div>
      <?php elseif (!empty($reviewComment)): ?>
        <p class="review-generated"><?= e($reviewComment) ?></p>
        <p class="review-generated-note">※ 楽天トラベルの軸別評価データをもとにした自動生成コメントです。</p>
      <?php endif; ?>

      <?php if ($hasReview): ?>
        <?php // CTR対策: 注記文ではなくアクション型のテキストリンクに(クチコミ全件ページへ) ?>
        <a class="review-more-link" href="<?= e($reviewMoreUrl ?? $ctaUrl) ?>" target="_blank"
           rel="sponsored nofollow noopener" data-track="review_more" data-hotel="<?= e($hotel['hotelNo']) ?>">
          クチコミ<?= e(number_format((int) $hotel['reviewCount'])) ?>件をすべて読む(楽天トラベル)
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
        </a>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php
  // === 5. 写真(フォトギャラリー + 客室を1セクションに統合) ===
  // ※ Instagram「みんなの投稿」枠はこの直前に配置(依頼B-4)。トークン未設定時は自動非表示。
  ?>
  <?php
  $gallery = $hotel['hotelImageUrls'];
  $hasGallery = count($gallery) > 1;
  $hasRooms = !empty($hotel['roomImageUrls']);
  ?>
  <?php if ($hasGallery || $hasRooms): ?>
    <section class="block reveal">
      <h2 class="section-title"><span class="bar"></span>写真</h2>
      <?php if ($hasGallery):
        $feat = $gallery[0];
        $rest = array_slice($gallery, 1, 5);
        $remain = max(0, count($gallery) - 6);
      ?>
        <div class="gallery-grid">
          <a class="feat" href="<?= e($feat) ?>" target="_blank" rel="noopener"><img src="<?= e($feat) ?>" alt="<?= e($hotel['hotelName']) ?>" loading="lazy"></a>
          <?php foreach ($rest as $i => $u): ?>
            <?php $isLast = ($i === count($rest) - 1) && $remain > 0; ?>
            <a<?= $isLast ? ' class="more" data-more="+' . e((string) $remain) . '"' : '' ?> href="<?= e($u) ?>" target="_blank" rel="noopener"><img src="<?= e($u) ?>" alt="<?= e($hotel['hotelName'] . 'の写真' . ($i + 2) . '枚目') ?>" loading="lazy"></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($hasRooms): ?>
        <h3 class="section-subtitle">客室</h3>
        <div class="room-track">
          <?php foreach ($hotel['roomImageUrls'] as $u): ?>
            <div class="room-shot"><img src="<?= e($u) ?>" alt="客室" loading="lazy"></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php // メモ1: 楽天APIの写真は少ないため、Instagramのハッシュタグ検索を補助導線に ?>
      <?php component('instagram', ['instagramUrl' => $instagramUrl ?? null]); ?>
    </section>
  <?php endif; ?>

  <?php // === 6. 温泉・お風呂(3-3: bathType / bathQuality + 泉質辞書) === ?>
  <?php if ($hotel['bathType'] !== '' || $hotel['bathQuality'] !== '' || !empty($onsenNote)): ?>
    <section class="block reveal">
      <h2 class="section-title"><span class="bar"></span>温泉・お風呂</h2>
      <ul class="facility-list">
        <?php if ($hotel['bathType'] !== ''): ?>
          <li><span class="k">お風呂</span><span><?= e($hotel['bathType']) ?></span></li>
        <?php endif; ?>
        <?php if ($hotel['bathQuality'] !== ''): ?>
          <li><span class="k">泉質</span><span><?= e($hotel['bathQuality']) ?></span></li>
        <?php endif; ?>
        <?php if (!empty($hotel['bathBenefits'])): ?>
          <li><span class="k">効能(掲示)</span><span><?= e($hotel['bathBenefits']) ?></span></li>
        <?php endif; ?>
      </ul>
      <?php if (!empty($onsenNote)): ?>
        <div class="onsen-note">
          <div class="onsen-note-label"><?= e($onsenNote['label']) ?>とは</div>
          <p><?= e($onsenNote['desc']) ?></p>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php // === 6.5 温泉地ガイド(増強①: 辞書から温泉地の解説を表示) === ?>
  <?php if (!empty($onsenArea)): ?>
    <section class="block reveal">
      <h2 class="section-title"><span class="bar"></span><?= e($onsenArea['name']) ?>という温泉地</h2>
      <p class="onsen-area-desc"><?= e($onsenArea['desc']) ?></p>
      <?php $areaHref = !empty($onsenArea['key'])
          ? '/area/' . rawurlencode($onsenArea['key'])
          : '/search?q=' . rawurlencode($onsenArea['searchQuery']); ?>
      <a class="onsen-area-link" href="<?= e($areaHref) ?>"
         data-track="onsen_area_search" data-category="<?= e($onsenArea['name']) ?>">
        <?= e($onsenArea['name']) ?>の宿ランキングを見る
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
      </a>
    </section>
  <?php endif; ?>

  <?php // === 7. 館内・基本情報(旧quickbarのIN/OUT/駐車場を吸収) + FAQ === ?>
  <?php
  $basics = [];
  if ($hotel['checkinTime'])  { $basics[] = ['k' => 'チェックイン', 'v' => $hotel['checkinTime']]; }
  if ($hotel['checkoutTime']) { $basics[] = ['k' => 'チェックアウト', 'v' => $hotel['checkoutTime']]; }
  if ($hotel['parkingInformation']) { $basics[] = ['k' => '駐車場', 'v' => $hotel['parkingInformation']]; }
  if (($hotel['minCharge'] ?? 0) > 0) { $basics[] = ['k' => '料金の目安', 'v' => '1泊 ' . number_format((int) $hotel['minCharge']) . '円〜']; }
  // 実データ対応: facilities 配列には IN/OUT・駐車場・風呂・泉質・最寄り駅なども
  // 含まれるため、basics/温泉セクション/アクセスと重複するキーはここでは出さない。
  $shownKeys = ['チェックイン', 'チェックアウト', '駐車場', '料金の目安', '風呂', 'お風呂', '泉質', '効能', '最寄り駅'];
  $extraFacilities = array_values(array_filter(
      (array) $hotel['facilities'],
      static fn ($f): bool => !in_array((string) ($f['k'] ?? ''), $shownKeys, true)
  ));
  ?>
  <?php if ($basics !== [] || $extraFacilities !== [] || !empty($faq)): ?>
    <section class="block reveal">
      <h2 class="section-title"><span class="bar"></span>館内・基本情報</h2>
      <?php if ($basics !== [] || $extraFacilities !== []): ?>
        <ul class="facility-list">
          <?php foreach ($basics as $f): ?>
            <li><span class="k"><?= e($f['k']) ?></span><span><?= e($f['v']) ?></span></li>
          <?php endforeach; ?>
          <?php foreach ($extraFacilities as $f): ?>
            <li><span class="k"><?= e($f['k']) ?></span><span><?= e($f['v']) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if (!empty($faq)): ?>
        <h3 class="section-subtitle">よくある質問</h3>
        <div class="faq-list">
          <?php foreach ($faq as $f): ?>
            <details class="faq-item">
              <summary><?= e($f['q']) ?></summary>
              <p><?= e($f['a']) ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php // === 7.5 このエリアでの位置づけ(増強2・3: 市の文脈 + 近隣比較) === ?>
  <?php
  $hasCityCtx = !empty($cityStats['cnt']) && (int) $cityStats['cnt'] >= 3;
  $hasCompare = !empty($nearbyCompare);
  ?>
  <?php if ($hasCityCtx || $hasCompare): ?>
    <section class="block reveal">
      <h2 class="section-title"><span class="bar"></span><?= e($hasCityCtx ? $city . 'での位置づけ' : '近くの宿と比べる') ?></h2>

      <?php if ($hasCityCtx): ?>
        <p class="city-context">
          <?= e($city) ?>には当サイト掲載の旅館が<?= e((string) $cityStats['cnt']) ?>軒あり、
          この宿はクチコミ数で<?= e((string) $cityStats['rankByReviews']) ?>位、
          総合評価<?= e(number_format((float) $hotel['reviewAverage'], 1)) ?>は
          エリア平均<?= e(number_format((float) $cityStats['avgReview'], 1)) ?>
          <?= (float) $hotel['reviewAverage'] >= (float) $cityStats['avgReview'] ? 'を上回ります' : 'に対する水準です' ?>。
        </p>
      <?php endif; ?>

      <?php if ($hasCompare): ?>
        <div class="compare-table-wrap">
          <table class="compare-table">
            <thead>
              <tr><th>宿</th><th>総合</th><th>クチコミ</th><th>風呂</th><th>料金目安</th><th>距離</th></tr>
            </thead>
            <tbody>
              <tr class="compare-self">
                <td class="cmp-name"><?= e($hotel['hotelName']) ?><span class="cmp-badge">この宿</span></td>
                <td>★<?= e(number_format((float) $hotel['reviewAverage'], 1)) ?></td>
                <td><?= e(number_format((int) $hotel['reviewCount'])) ?>件</td>
                <td><?= !empty($hotel['axis']['bath']['value']) ? '★' . e(number_format((float) $hotel['axis']['bath']['value'], 1)) : '-' ?></td>
                <td><?= ($hotel['minCharge'] ?? 0) > 0 ? e(number_format((int) $hotel['minCharge'])) . '円〜' : '-' ?></td>
                <td>-</td>
              </tr>
              <?php foreach ($nearbyCompare as $n): ?>
                <tr>
                  <td class="cmp-name"><a href="/hotel/<?= e(rawurlencode((string) $n['hotelNo'])) ?>"><?= e($n['hotelName']) ?></a></td>
                  <td><?= (float) $n['reviewAverage'] > 0 ? '★' . e(number_format((float) $n['reviewAverage'], 1)) : '-' ?></td>
                  <td><?= e(number_format((int) $n['reviewCount'])) ?>件</td>
                  <td><?= (float) ($n['bathValue'] ?? 0) > 0 ? '★' . e(number_format((float) $n['bathValue'], 1)) : '-' ?></td>
                  <td><?= (int) ($n['minCharge'] ?? 0) > 0 ? e(number_format((int) $n['minCharge'])) . '円〜' : '-' ?></td>
                  <td><?= e(number_format((float) ($n['distKm'] ?? 0), 1)) ?>km</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="compare-note">近隣は半径10km以内・クチコミ数順の上位。料金は時期・プランで変動します。</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php // === 8. アクセスと周辺(地図 + 最寄り駅 + 周辺スポットを統合) === ?>
  <?php $hasMap = $hotel['latitude'] !== null && $hotel['longitude'] !== null; ?>
  <?php if ($hasMap || $hotel['access'] || !empty($surroundings)): ?>
    <section class="block reveal">
      <h2 class="section-title"><span class="bar"></span>アクセスと周辺</h2>
      <?php if ($hasMap): ?>
        <?php // Googleマップ埋め込み(APIキー不要のembed)。表示崩れのあったLeaflet/OSMから置換 ?>
        <?php $mapQ = rawurlencode($hotel['latitude'] . ',' . $hotel['longitude']); ?>
        <div class="map-box">
          <iframe class="gmap-embed"
                  src="https://maps.google.com/maps?q=<?= e($mapQ) ?>&z=15&hl=ja&output=embed"
                  loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                  allowfullscreen title="<?= e($hotel['hotelName']) ?>の地図"></iframe>
        </div>
        <a class="gmap-open" href="https://www.google.com/maps/search/?api=1&query=<?= e($mapQ) ?>"
           target="_blank" rel="noopener nofollow">
          Googleマップで開く
          <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>
        </a>
      <?php endif; ?>
      <div class="access-list">
        <?php if ($hotel['nearestStation']): ?><p><span class="k">最寄り駅</span><?= e($hotel['nearestStation']) ?></p><?php endif; ?>
        <?php if ($hotel['access']): ?><p><span class="k">アクセス</span><?= e($hotel['access']) ?></p><?php endif; ?>
      </div>

      <?php if (!empty($surroundings)): ?>
        <h3 class="section-subtitle">周辺スポット</h3>
        <?php if (($surroundings['source'] ?? '') === 'rakuten' && !empty($surroundings['leisureText'])): ?>
          <?php // 実データ対応: 「海水浴,釣り,テニス,...」のカンマ羅列をチップに整形 ?>
          <?php $leisure = array_filter(array_map('trim', preg_split('/[、,・\/]/u', (string) $surroundings['leisureText']) ?: [])); ?>
          <?php if (count($leisure) > 1): ?>
            <div class="leisure-chips">
              <?php foreach (array_slice($leisure, 0, 12) as $l): ?>
                <span class="leisure-chip"><?= e($l) ?></span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="leisure-text"><?= e($surroundings['leisureText']) ?></p>
          <?php endif; ?>
        <?php elseif (!empty($surroundings['spots'])): ?>
          <ul class="spot-list">
            <?php foreach ($surroundings['spots'] as $sp): ?>
              <li>
                <span class="spot-name"><?= e($sp['name']) ?></span>
                <span class="spot-dist">約<?= e((string) (max(1, (int) round($sp['dist'] / 100)) * 100)) ?>m</span>
              </li>
            <?php endforeach; ?>
          </ul>
          <p class="map-attr">周辺情報: © OpenStreetMap contributors</p>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php // === 9. 空室確認CTA === ?>
  <section class="block reveal">
    <div class="cta-block">
      <p class="lead-copy">このお宿の空室と料金は<br>楽天トラベルで確認できます</p>
      <a class="btn-primary-lg" href="<?= e($ctaUrl) ?>" target="_blank" rel="sponsored nofollow noopener"
         data-track="detail_cta" data-hotel="<?= e($hotel['hotelNo']) ?>">
        楽天トラベルで空室・料金を見る
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>
      </a>
      <p class="cta-disclaimer">楽天トラベル(外部サイト)へ移動します</p>
      <?php if (!empty($hotel['fetchedAt'])): ?>
        <p class="cta-updated">情報の最終更新：<?= e(date('Y年n月', (int) $hotel['fetchedAt'])) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <?php // === 10. おすすめレール(近くの宿 / 同じ県 / タグ。重複除去済み) === ?>
  <?php foreach ($rails as $rail): ?>
    <?php component('horizontal-rail', [
        'title'    => $rail['title'],
        'items'    => $rail['items'],
        'cardType' => 'poster',
        'key'      => $rail['key'] ?? '',
        'moreHref' => $rail['moreHref'] ?? '/categories',
    ]); ?>
  <?php endforeach; ?>

  <?php // === 11. 最近見た宿(JSがLocalStorageから描画) === ?>
  <section class="rail reveal" id="recentRail" hidden>
    <div class="rail-head"><h2><span class="bar"></span>最近見た宿</h2></div>
    <div class="rail-track" data-recent-track></div>
  </section>

  <?php // === 12. 旅をもっと楽しむ(アフィリンク設定済みの項目のみ表示) === ?>
  <?php
  // 免許合宿・レンタカーはコンテンツ・提携リンク未整備のため、
  // env に「http(s)で始まる有効なURL」が入っている項目だけ出す(未設定/#/空=非表示)。
  $rentacarUrl = trim((string) ($xsell['rentacar_url'] ?? ''));
  $gasshukuUrl = trim((string) ($xsell['gasshuku_url'] ?? ''));
  $showRentacar = str_starts_with($rentacarUrl, 'http');
  $showGasshuku = str_starts_with($gasshukuUrl, 'http');
  ?>
  <?php if ($showRentacar || $showGasshuku): ?>
    <section class="block reveal enjoy-more" style="border-bottom:none;">
      <h2 class="section-title"><span class="bar"></span>旅をもっと楽しむ</h2>
      <?php if ($showRentacar): ?>
        <div class="xsell">
          <h3>レンタカーで足をのばす</h3>
          <p>周辺の観光や、駅から離れた宿へのアクセスに。</p>
          <a class="btn-secondary" href="<?= e($rentacarUrl) ?>" target="_blank" rel="sponsored nofollow noopener">レンタカーを探す</a>
        </div>
      <?php endif; ?>
      <?php if ($showGasshuku): ?>
        <div class="xsell">
          <h3>合宿免許で長期滞在</h3>
          <p>免許取得の合宿プランをお探しの方へ。</p>
          <a class="btn-secondary" href="<?= e($gasshukuUrl) ?>" target="_blank" rel="sponsored nofollow noopener">合宿免許を探す</a>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php component('site-footer'); ?>

  <?php // JSレール(最近見た宿 等)がサーバー描画済みの宿を除外できるように共有 ?>
  <script>window.__seenHotelNos = <?= json_encode(array_values(array_map('strval', $seenHotelNos ?? [])), JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>

  <?php // パンくずの構造化データ(SEO) ?>
  <?php if (!empty($breadcrumb)): ?>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => array_map(function ($bc, $i) use ($hotel) {
            // item(URL)は最後の項目以外で必須。省略すると Search Console で
            // 「項目 item がありません」として無効扱いになるため、全項目に持たせる。
            // href が空の項目(=最後の自ページ)は自身のURLを使う。
            $base = rtrim((string) config('app.url'), '/');
            $url = !empty($bc['href'])
                ? $base . $bc['href']
                : $base . '/hotel/' . rawurlencode((string) $hotel['hotelNo']);
            return [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $bc['label'],
                'item'     => $url,
            ];
        }, $breadcrumb, array_keys($breadcrumb)),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <?php endif; ?>
</div>
