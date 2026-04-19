/**
 * AI traffic detection for the edge proxy.
 * Identifies AI crawlers (by User-Agent) and users arriving from AI
 * interfaces (by Referer). Returns normalized bot_type matching the
 * backend's AiVisitController::BOT_TYPES.
 */

export type AiSource = 'bot' | 'user';
export type AiBotType =
  | 'claude'
  | 'gpt'
  | 'perplexity'
  | 'google_ai'
  | 'apple'
  | 'bytedance'
  | 'meta'
  | 'amazon'
  | 'common_crawl'
  | 'cohere'
  | 'other';

export interface AiDetection {
  botType: AiBotType;
  source: AiSource;
}

// Order matters — first match wins. Regex tested against the raw UA string.
const BOT_UA_RULES: Array<{ pattern: RegExp; type: AiBotType }> = [
  { pattern: /(ClaudeBot|Claude-SearchBot|Claude-User|anthropic-ai)/i, type: 'claude' },
  { pattern: /(GPTBot|ChatGPT-User|OAI-SearchBot)/i, type: 'gpt' },
  { pattern: /(PerplexityBot|Perplexity-User)/i, type: 'perplexity' },
  { pattern: /(Google-Extended|GoogleOther)/i, type: 'google_ai' },
  // NOTE: plain "Applebot" is Apple's regular search/Siri index — not AI.
  // Only "-Extended" opts into AI training scope.
  { pattern: /Applebot-Extended/i, type: 'apple' },
  { pattern: /Bytespider/i, type: 'bytedance' },
  { pattern: /(Meta-ExternalAgent|meta-externalfetcher|FacebookBot)/i, type: 'meta' },
  { pattern: /Amazonbot/i, type: 'amazon' },
  { pattern: /CCBot/i, type: 'common_crawl' },
  { pattern: /cohere-ai/i, type: 'cohere' },
];

const AI_REFERER_HOSTS: Array<{ pattern: RegExp; type: AiBotType }> = [
  { pattern: /(^|\.)chatgpt\.com$|(^|\.)chat\.openai\.com$/i, type: 'gpt' },
  { pattern: /(^|\.)claude\.ai$/i, type: 'claude' },
  { pattern: /(^|\.)perplexity\.ai$/i, type: 'perplexity' },
  { pattern: /(^|\.)copilot\.microsoft\.com$/i, type: 'gpt' },
  { pattern: /(^|\.)gemini\.google\.com$|(^|\.)bard\.google\.com$/i, type: 'google_ai' },
  { pattern: /(^|\.)you\.com$/i, type: 'other' },
  { pattern: /(^|\.)phind\.com$/i, type: 'other' },
];

export function detectAiTraffic(userAgent: string | null, referer: string | null): AiDetection | null {
  if (userAgent) {
    for (const rule of BOT_UA_RULES) {
      if (rule.pattern.test(userAgent)) {
        return { botType: rule.type, source: 'bot' };
      }
    }
  }

  if (referer) {
    let host: string;
    try {
      host = new URL(referer).hostname;
    } catch {
      return null;
    }
    for (const rule of AI_REFERER_HOSTS) {
      if (rule.pattern.test(host)) {
        return { botType: rule.type, source: 'user' };
      }
    }
  }

  return null;
}
