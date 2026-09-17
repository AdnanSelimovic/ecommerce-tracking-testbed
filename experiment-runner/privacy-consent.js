export const CONSENT_PROFILES = Object.freeze({
  full: Object.freeze({ analytics_storage: 'granted', ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' }),
  partial: Object.freeze({ analytics_storage: 'granted', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' }),
  none: Object.freeze({ analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' }),
});
export function consentProfile(mode) {
  if (!Object.hasOwn(CONSENT_PROFILES, mode)) throw new Error('unknown consent mode');
  return CONSENT_PROFILES[mode];
}
