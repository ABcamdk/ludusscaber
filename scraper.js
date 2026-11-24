const express = require('express');
const { chromium } = require('playwright');

const app = express();
app.use(express.json());

app.post('/scrape', async (req, res) => {
  const { url, username, password, weeksToScrape = 2 } = req.body;

  if (!url || !username || !password) {
    return res.status(400).json({ error: 'Manglende URL, brugernavn eller adgangskode.' });
  }

  let browser;
  try {
    browser = await chromium.launch({
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();

    // 1. Gå til Ludus URL
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });

    // 2. Vent på Vaadin login-felter og simuler rigtige tastetryk
    await page.waitForSelector('#brugerIdField', { state: 'visible', timeout: 20000 });
    await page.click('#brugerIdField');
    await page.locator('#brugerIdField').pressSequentially(username, { delay: 30 });
    await page.locator('#brugerIdField').dispatchEvent('change');

    await page.click('#kodeordField');
    await page.locator('#kodeordField').pressSequentially(password, { delay: 30 });
    await page.locator('#kodeordField').dispatchEvent('change');

    // Klik på OK-knappen (.v-button indeholdende "OK")
    const okButton = page.locator('.v-button', { has: page.locator('.v-button-caption', { hasText: /^OK$/ }) }).first();
    await okButton.click();

    // 3. Bekræft login ved at vente på topmenuen (.v-menubar)
    try {
      await page.waitForSelector('.v-menubar', { state: 'visible', timeout: 15000 });
    } catch {
      return res.status(401).json({ error: 'Login fejlede. Tjek brugernavn, adgangskode og Ludus URL.' });
    }

    // 4. Åbn "Skemaer"-fanen
    const skemaClicked = await page.evaluate(() => {
      const captions = Array.from(document.querySelectorAll('.v-menubar .v-menubar-menuitem-caption'));
      const target = captions.find(el => el.innerText.trim() === 'Skemaer');
      if (target) {
        const menuItem = target.closest('.v-menubar-menuitem');
        if (menuItem) {
          menuItem.click();
          return true;
        }
      }
      return false;
    });

    if (!skemaClicked) {
      throw new Error('Kunne ikke finde menupunktet "Skemaer" i topmenuen.');
    }

    // Vent på at skemaets uge-navigation og brikker er indlæst
    await page.waitForSelector('.fa-chevron-right', { state: 'visible', timeout: 15000 });
    await page.waitForTimeout(1500); // Giv Vaadin et øjeblik til at tegne brikkerne

    const scrapedWeeks = [];

    // 5. Skrab det ønskede antal uger
    for (let w = 0; w < Math.min(weeksToScrape, 4); w++) {
      const weekData = await page.evaluate(() => {
        const weekLabelEl = Array.from(document.querySelectorAll('.v-label-bold'))
          .find(el => /uge\s*\d+/i.test(el.innerText));
        const weekTitle = weekLabelEl ? weekLabelEl.innerText.trim() : 'Ukendt uge';

        const days = ['Mandag', 'Tirsdag', 'Onsdag', 'Torsdag', 'Fredag'];
        const schedule = { Mandag: [], Tirsdag: [], Onsdag: [], Torsdag: [], Fredag: [] };

        const selectors = [
          '.ugeskema-skemabrik-element',
          '.ugeskema-aendret-lektion-element',
          '.ugeskema-aflyst-lektion-element'
        ].join(', ');

        const bricks = Array.from(document.querySelectorAll(selectors))
          .filter(el => el.getBoundingClientRect().width > 0);

        bricks.forEach(brick => {
          const wrapper = brick.closest('.v-absolutelayout-wrapper');
          if (!wrapper) return;

          const leftStr = wrapper.style.left || '0%';
          const topStr = wrapper.style.top || '0px';

          const leftPercent = parseFloat(leftStr);
          const topPx = parseFloat(topStr);

          // Bestem ugedag ud fra left % (0, 20, 40, 60, 80)
          const dayIndex = Math.min(4, Math.max(0, Math.round(leftPercent / 20)));
          const dayName = days[dayIndex];

          // Bestem type
          let status = 'normal';
          if (brick.classList.contains('ugeskema-aendret-lektion-element')) status = 'aendret';
          if (brick.classList.contains('ugeskema-aflyst-lektion-element')) status = 'aflyst';

          // Udtræk tekstindhold
          const subjectEl = brick.querySelector('.v-label-text-ellipsis:not(.small)') || brick.querySelector('.v-button-caption');
          const smallLabels = Array.from(brick.querySelectorAll('.v-label-small')).map(el => el.innerText.trim()).filter(Boolean);
          const teacherEl = brick.querySelector('u');
          const hasHomework = Boolean(brick.querySelector('.fa-book'));

          schedule[dayName].push({
            subject: subjectEl ? subjectEl.innerText.trim() : 'Ukendt fag',
            time: smallLabels[0] || '',
            room: smallLabels[1] || '',
            teacher: teacherEl ? teacherEl.innerText.trim() : '',
            status,
            hasHomework,
            topPx
          });
        });

        // Sorter lektioner kronologisk efter topPx
        days.forEach(d => {
          schedule[d].sort((a, b) => a.topPx - b.topPx);
        });

        return { weekTitle, schedule };
      });

      scrapedWeeks.push(weekData);

      // Gå til næste uge, hvis vi skal skrabe flere
      if (w < Math.min(weeksToScrape, 4) - 1) {
        const currentTitle = weekData.weekTitle;
        await page.evaluate(() => {
          const nextIcon = document.querySelector('.fa-chevron-right');
          const btn = nextIcon ? nextIcon.closest('[role="button"]') : null;
          if (btn) btn.click();
        });

        // Vent på at ugeteksten ændrer sig
        await page.waitForFunction(
          (prev) => {
            const el = Array.from(document.querySelectorAll('.v-label-bold'))
              .find(e => /uge\s*\d+/i.test(e.innerText));
            return el && el.innerText.trim() !== prev;
          },
          currentTitle,
          { timeout: 10000 }
        ).catch(() => {});

        await page.waitForTimeout(1000);
      }
    }

    res.json({ success: true, weeks: scrapedWeeks });

  } catch (err) {
    res.status(500).json({ error: err.message || 'Der opstod en fejl under scraping.' });
  } finally {
    if (browser) await browser.close();
  }
});

app.listen(3001, '127.0.0.1', () => {
  console.log('Ludus scraper API kører på http://127.0.0.1:3001');
});