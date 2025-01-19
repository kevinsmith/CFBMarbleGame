import { test, expect } from '@playwright/test';

test('landing page', async ({ page }) => {
  await page.goto('/');

  await expect(page).toHaveTitle('CFB Marble Game');
  await expect(page.getByRole('heading', { name: 'CFB Marble Game' })).toBeVisible();
});

test('returns 404 not found', async ({ request }) => {
    const response = await request.get('/nonexistent-route-SiwyTfsfZ8t8KGInW71B');
    expect(response.status()).toBe(404);
});

test('returns 405 method not allowed', async ({ request }) => {
    const response = await request.patch('/QZfvbotRlJ/test-routes/plain');
    expect(response.status()).toBe(405);
});

test('checks plain route', async ({ request }) => {
    const response = await request.get('/QZfvbotRlJ/test-routes/plain');
    expect(response.status()).toBe(200);

});

test('checks all route args', async ({ request }) => {
    const firstArgValue = 'xsRgNrcO3Q2xXtHs0CwK';
    const secondArgValue = '39259826';

    const response = await request.get(`/QZfvbotRlJ/test-routes/only-args/${firstArgValue}/${secondArgValue}`);
    expect(response.status()).toBe(200);
    expect(response.headers()["content-type"]).toBe("application/json");

    const body = await response.json();
    expect(body.first).toBe(firstArgValue);
    expect(body.second).toBe(secondArgValue);
});

test('checks query param', async ({ request }) => {
    const paramValue = 'yEAJXV2LnKU2hqhgyoPQ';

    const response = await request.get(`/QZfvbotRlJ/test-routes/only-query-param?param=${paramValue}`);
    expect(response.status()).toBe(200);
    expect(response.headers()["content-type"]).toBe("application/json");

    const body = await response.json();
    expect(body.param).toBe(paramValue);
});

test('checks all route args and query param', async ({ request }) => {
    const requiredArgValue = 'T14HNjEDT6MryWmZkuzV';
    const optionalNumericArgValue = '2432302';
    const paramValue = 'zvEvFg86KWq3aRmxS65E';

    const response = await request.get(`/QZfvbotRlJ/test-routes/args-and-query-param/${requiredArgValue}/${optionalNumericArgValue}?param=${paramValue}`);
    expect(response.status()).toBe(200);
    expect(response.headers()["content-type"]).toBe("application/json");

    const body = await response.json();
    expect(body.required).toBe(requiredArgValue);
    expect(body.optional).toBe(optionalNumericArgValue);
    expect(body.param).toBe(paramValue);
});

test('checks required route arg and query param', async ({ request }) => {
    const requiredArgValue = 'vt1D9hTOW41HiQj8pFem';
    const paramValue = 'SmB10FhAIJmeNTMhtmk9';

    const response = await request.get(`/QZfvbotRlJ/test-routes/args-and-query-param/${requiredArgValue}?param=${paramValue}`);
    expect(response.status()).toBe(200);
    expect(response.headers()["content-type"]).toBe("application/json");

    const body = await response.json();
    expect(body.required).toBe(requiredArgValue);
    expect(body.optional).toBe('38240');
    expect(body.param).toBe(paramValue);
});
