// tests/E2E/playwright-scripts/manifestation.spec.js
const { test, expect } = require('@playwright/test');

test('表のヘッダーが正しく翻訳されている', async ({ page }) => {
    // アプリケーションの該当ページに移動
    await page.goto('http://localhost:8000/manifestation'); // 実際のURLに合わせて変更

    // ヘッダーテキストを検証
    const headers = [
        'ID',
        'タイトル',
        '識別子',
        '外部識別子1',
        '外部識別子2',
        '外部識別子3',
        '説明',
        '購入日'
    ];

    for (const header of headers) {
        const headerElement = await page.locator(`th:has-text("${header}")`);
        await expect(headerElement).toBeVisible();
    }
});

test('表に内容が表示されている', async ({ page }) => {
    await page.goto('http://localhost:8000/manifestation'); // 実際のURLに合わせて変更

    // テーブルに行があることを確認
    const rows = await page.locator('table tbody tr');
    await expect(rows).toHaveCount({ min: 1 });

    // 特定のテキストが表示されていることを確認（実際のデータに合わせて変更）
    await expect(page.locator('table')).toContainText('書誌');
});

test.describe('Manifestation CRUD操作', () => {
    const baseUrl = 'http://localhost:8000'; // 実際の環境URLに変更してください

    test.beforeEach(async ({ page }) => {
        // ログイン処理（必要な場合）
        await page.goto(`${baseUrl}/login`);
        await page.fill('input[name="username"]', 'admin'); // 実際のユーザー名に変更
        await page.fill('input[name="password"]', 'password'); // 実際のパスワードに変更
        await page.click('button[type="submit"]');

        // ログイン後のリダイレクトを待つ
        await page.waitForURL(`${baseUrl}/**`);
    });

    // Create - 新規作成のテスト
    test('新しいManifestationを作成できる', async ({ page }) => {
        // Manifestation作成ページに移動
        await page.goto(`${baseUrl}/manifestation/new`);

        // フォームに入力
        await page.fill('input[name="manifestation[title]"]', 'テスト書誌タイトル');
        await page.fill('input[name="manifestation[identifier]"]', 'TEST-ID-001');
        await page.fill('input[name="manifestation[external_identifier1]"]', 'EXT-001');
        await page.fill('input[name="manifestation[external_identifier2]"]', 'EXT-002');
        await page.fill('input[name="manifestation[external_identifier3]"]', 'EXT-003');
        await page.fill('textarea[name="manifestation[description]"]', 'これはテスト用の書誌説明です。');
        await page.fill('input[name="manifestation[purchase_date]"]', '2023-01-01');

        // 保存ボタンをクリック
        await page.click('button[type="submit"]');

        // 作成成功の確認
        await expect(page.locator('.alert-success')).toBeVisible();
        await expect(page.locator('.alert-success')).toContainText('作成しました');

        // 作成したデータが表示されているか確認
        await expect(page.locator('body')).toContainText('テスト書誌タイトル');
    });

    // Read - 一覧表示と詳細表示のテスト
    test('Manifestation一覧が表示され、詳細を確認できる', async ({ page }) => {
        // 一覧ページに移動
        await page.goto(`${baseUrl}/manifestation`);

        // テーブルヘッダーが正しく表示されていることを確認
        const headers = ['ID', 'タイトル', '識別子', '外部識別子1', '外部識別子2', '外部識別子3', '説明', '購入日'];
        for (const header of headers) {
            await expect(page.locator(`th:has-text("${header}")`)).toBeVisible();
        }

        // テーブルに少なくとも1行のデータがあることを確認
        await expect(page.locator('tbody tr')).toHaveCount({ min: 1 });

        // 作成したデータの詳細を確認
        // 前のテストで作成したデータを見つける
        const row = page.locator('tbody tr', { hasText: 'テスト書誌タイトル' }).first();

        // 詳細ボタンをクリック
        await row.locator('a:has-text("詳細")').click();

        // 詳細ページに必要な情報が表示されていることを確認
        await expect(page.locator('body')).toContainText('テスト書誌タイトル');
        await expect(page.locator('body')).toContainText('TEST-ID-001');
        await expect(page.locator('body')).toContainText('これはテスト用の書誌説明です。');
    });

    // Update - 更新のテスト
    test('既存のManifestationを更新できる', async ({ page }) => {
        // 一覧ページに移動
        await page.goto(`${baseUrl}/manifestation`);

        // 作成したデータの行を見つける
        const row = page.locator('tbody tr', { hasText: 'テスト書誌タイトル' }).first();

        // 編集ボタンをクリック
        await row.locator('a:has-text("編集")').click();

        // フォームに新しい値を入力
        await page.fill('input[name="manifestation[title]"]', '更新されたタイトル');
        await page.fill('textarea[name="manifestation[description]"]', '更新された説明文です。');

        // 保存ボタンをクリック
        await page.click('button[type="submit"]');

        // 更新成功の確認
        await expect(page.locator('.alert-success')).toBeVisible();
        await expect(page.locator('.alert-success')).toContainText('更新しました');

        // 更新されたデータが表示されているか確認
        await expect(page.locator('body')).toContainText('更新されたタイトル');
        await expect(page.locator('body')).toContainText('更新された説明文です。');
    });

    // Delete - 削除のテスト
    test('Manifestationを削除できる', async ({ page }) => {
        // 一覧ページに移動
        await page.goto(`${baseUrl}/manifestation`);

        // 更新したデータの行を見つける
        const row = page.locator('tbody tr', { hasText: '更新されたタイトル' }).first();

        // 削除前のレコード数を記録
        const beforeCount = await page.locator('tbody tr').count();

        // 削除ボタンをクリック
        await row.locator('button:has-text("削除")').click();

        // 確認ダイアログが表示されたら確認
        page.on('dialog', async dialog => {
            expect(dialog.type()).toBe('confirm');
            await dialog.accept();
        });

        // 削除成功メッセージを確認
        await expect(page.locator('.alert-success')).toBeVisible();
        await expect(page.locator('.alert-success')).toContainText('削除しました');

        // 削除後のレコード数を確認
        const afterCount = await page.locator('tbody tr').count();
        expect(afterCount).toBe(beforeCount - 1);

        // 削除されたデータが表示されていないことを確認
        await expect(page.locator('body')).not.toContainText('更新されたタイトル');
    });

    // フィルター/検索機能のテスト（オプション）
    test('Manifestationを検索できる', async ({ page }) => {
        // 一覧ページに移動
        await page.goto(`${baseUrl}/manifestation`);

        // 検索フォームに値を入力
        await page.fill('input[name="search"]', '書誌');
        await page.click('button:has-text("検索")');

        // 検索結果を確認
        await expect(page.locator('tbody tr')).toHaveCount({ min: 1 });

        // 検索結果にキーワードが含まれていることを確認
        const rows = await page.locator('tbody tr').all();
        let foundMatch = false;

        for (const row of rows) {
            const text = await row.textContent();
            if (text.includes('書誌')) {
                foundMatch = true;
                break;
            }
        }

        expect(foundMatch).toBeTruthy();
    });

    // バリデーションのテスト
    test('必須項目の入力検証が機能する', async ({ page }) => {
        // 新規作成ページに移動
        await page.goto(`${baseUrl}/manifestation/new`);

        // タイトルなど必須項目を空のままにする
        await page.fill('input[name="manifestation[title]"]', '');

        // 保存ボタンをクリック
        await page.click('button[type="submit"]');

        // バリデーションエラーメッセージが表示されることを確認
        await expect(page.locator('.invalid-feedback')).toBeVisible();
        await expect(page.locator('.invalid-feedback')).toContainText('タイトルは必須です');
    });
});
