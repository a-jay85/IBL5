/**
 * Trivia Mode detection helper.
 *
 * When Trivia Mode is enabled in ibl_settings, ModuleAccessControl hides
 * Player, SeasonLeaderboards, and CareerLeaderboards modules, showing
 * "Sorry, this Module isn't active!" instead.
 */

export const MODULE_INACTIVE_TEXT = "Module isn't active";

export function isModuleInactive(body: string | null): boolean {
  return body?.includes(MODULE_INACTIVE_TEXT) ?? false;
}
