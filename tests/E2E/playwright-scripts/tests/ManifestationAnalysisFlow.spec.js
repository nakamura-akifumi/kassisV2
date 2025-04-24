// tests/e2e/analysis-flow.spec.js
const { test, expect } = require('@playwright/test');

test.describe('アナリシス検索と作成のフロー', () => {
  
  test('アナリシス検索と作成のフローをテストする', async ({ page }) => {
    // ステップ1: ホームページにアクセスして表示内容を確認する
    await page.goto('/');
    
    // ホームページの基本要素を確認
    await expect(page).toHaveTitle(/ホーム|トップページ|Welcome/);
    await expect(page.locator('h1')).toBeVisible();
    
    // ホームページに検索フォームがあることを確認
    const searchForm = page.locator('form[action*="search"]');
    await expect(searchForm).toBeVisible();
    
    // ステップ2: アナリシスで検索して結果が0件であることを確認
    await page.fill('input[name="q"]', 'アナリシス');
    await page.click('button[type="submit"]');
    
    // 検索結果ページに遷移したことを確認
    await expect(page.locator('h1, h2')).toContainText(/検索結果|Search Results/);
    
    // 検索結果が0件であることを確認
    await expect(page.locator('body')).toContainText(/見つかりませんでした|No results found|0件/);
    
    // スクリーンショットを撮影
    await page.screenshot({ path: 'search-no-results.png' });
    
    // ステップ3: Manifestation の新規作成でタイトルを「アナリシスアイ」で作成する
    // Manifestation作成ページへ移動
    await page.goto('/manifestation/new');
    
    // 新規作成フォームが表示されていることを確認
    await expect(page.locator('form')).toBeVisible();
    
    // フォームに入力
    await page.fill('#manifestation_title', 'アナリシスアイ');
    await page.fill('#manifestation_identifier', 'analysis-ai-' + Date.now());
    
    // 必須フィールドがあれば入力
    // 例: 必須フィールドがある場合は以下のように入力
    // await page.fill('#manifestation_description', 'アナリシスAIの説明文');
    
    // フォームを送信
    await page.click('button[type="submit"]');
    
    // 作成成功メッセージを確認
    await expect(page.locator('.alert-success, .flash-success')).toBeVisible();
    
    // スクリーンショットを撮影
    await page.screenshot({ path: 'manifestation-created.png' });
    
    // ステップ4: ホームに戻ってアナリシスで検索して1件表示されることを確認
    await page.goto('/');
    
    // 検索フォームが表示されていることを確認
    await expect(searchForm).toBeVisible();
    
    // 再度「アナリシス」で検索
    await page.fill('input[name="q"]', 'アナリシス');
    await page.click('button[type="submit"]');
    
    // 検索結果ページに遷移したことを確認
    await expect(page.locator('h1, h2')).toContainText(/検索結果|Search Results/);
    
    // 検索結果が1件表示されていることを確認
    // 結果数を確認するセレクタを調整してください
    await expect(page.locator('.result-count, .count')).toContainText('1');
    
    // 「アナリシスアイ」が検索結果に含まれていることを確認
    await expect(page.locator('.search-results, .results')).toContainText('アナリシスアイ');
    
    // 詳細を確認するためにタイトルをクリック
    await page.click('text=アナリシスアイ');
    
    // 詳細ページに遷移したことを確認
    await expect(page.locator('h1')).toContainText(/詳細|Detail/);
    await expect(page.locator('body')).toContainText('アナリシスアイ');
    
    // スクリーンショットを撮影
    await page.screenshot({ path: 'search-with-results.png' });
  });
});