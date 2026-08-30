export { default as Friendship } from './models/Friendship';
export { default as FriendshipRequest } from './models/FriendshipRequest';
export { default as FriendshipEvent } from './models/FriendshipEvent';

export function getContrastColor(hex: string): string {
  const clean = hex.replace('#', '');
  const r = parseInt(clean.slice(0, 2), 16);
  const g = parseInt(clean.slice(2, 4), 16);
  const b = parseInt(clean.slice(4, 6), 16);
  const brightness = (r * 299 + g * 587 + b * 114) / 1000;
  return brightness > 135 ? 'rgba(0,0,0,0.82)' : '#fff';
}
