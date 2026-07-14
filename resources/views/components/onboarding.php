<?php
/**
 * 初回オンボーディング(Netflix風「好きなジャンルを選んでください」)。
 * @var array $onboarding config('taxonomy.onboarding')
 *
 * 初回訪問時(localStorageに選択履歴が無いとき)のみ、JSが .is-open を付けて表示する。
 * ここでは常にDOMを出力し、表示制御と保存はJS(onboarding.js)に委ねる。
 * 選択したタグはトップの棚並び替え(C-7)や類似レコメンド(C-8)の初期値に使われる。
 */
$onboarding = $onboarding ?? [];
$cards = (array) ($onboarding['cards'] ?? []);
$minSelect = (int) ($onboarding['min_select'] ?? 2);
if ($cards === []) { return; }
?>
<div class="onboarding" id="onboarding" data-min-select="<?= e((string) $minSelect) ?>" aria-hidden="true">
  <div class="onboarding-sheet" role="dialog" aria-modal="true" aria-labelledby="onboardingTitle">
    <div class="onboarding-scroll">
      <div class="onboarding-head">
        <div class="eyebrow">はじめまして</div>
        <h2 id="onboardingTitle">どんな旅がお好みですか？</h2>
        <p class="onboarding-sub">選ぶほど、あなた好みの宿が上に並びます。<br>気になるものを<strong><?= e((string) $minSelect) ?>つ以上</strong>えらんでください。</p>
      </div>

      <div class="onboarding-grid" data-onboarding-grid>
        <?php foreach ($cards as $c): ?>
          <button type="button" class="ob-card"
                  data-ob-card
                  data-key="<?= e((string) ($c['key'] ?? '')) ?>"
                  data-axis="<?= e((string) ($c['axis'] ?? '')) ?>"
                  data-tags="<?= e(implode(',', (array) ($c['tags'] ?? []))) ?>"
                  aria-pressed="false">
            <span class="ob-emoji"><?= e((string) ($c['emoji'] ?? '')) ?></span>
            <span class="ob-label"><?= e((string) ($c['label'] ?? '')) ?></span>
            <span class="ob-check" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-11"/></svg>
            </span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="onboarding-foot">
      <button type="button" class="ob-skip" data-ob-skip>あとで</button>
      <button type="button" class="ob-done" data-ob-done disabled>
        <span data-ob-count>0</span>件で始める
      </button>
    </div>
  </div>
</div>
