import { readFileSync } from 'node:fs';
import { galleryRenderedViewsError } from './rendered-views';

const path = process.argv[2];
if (path === undefined || path === '') {
  console.error('usage: assert-gallery.ts <vr-gallery.json>');
  process.exit(2);
}
const err = galleryRenderedViewsError(JSON.parse(readFileSync(path, 'utf8')));
if (err !== null) {
  console.error(`::error::${err}`);
  process.exit(1);
}
console.log('renderedViewFiles present');
