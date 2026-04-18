'use client';

import { useState, useCallback } from 'react';

interface ValidationRule {
  required?: boolean;
  pattern?: RegExp;
  validator?: () => boolean; // custom check; returns true if valid
  message: string;
  when?: () => boolean; // conditional validation
}

type ValidationRules = Record<string, ValidationRule[]>;

export function useFormValidation() {
  const [errors, setErrors] = useState<Record<string, string>>({});

  const validate = useCallback(
    (data: Record<string, any>, rules: ValidationRules): boolean => {
      const newErrors: Record<string, string> = {};

      for (const [field, fieldRules] of Object.entries(rules)) {
        for (const rule of fieldRules) {
          // Skip if conditional rule and condition is false
          if (rule.when && !rule.when()) continue;

          const value = data[field];

          if (rule.required && (!value || String(value).trim() === '')) {
            newErrors[field] = rule.message;
            break;
          }

          if (rule.pattern && value && !rule.pattern.test(String(value))) {
            newErrors[field] = rule.message;
            break;
          }

          if (rule.validator && !rule.validator()) {
            newErrors[field] = rule.message;
            break;
          }
        }
      }

      setErrors(newErrors);

      // Scroll to first error
      const firstErrorField = Object.keys(newErrors)[0];
      if (firstErrorField) {
        setTimeout(() => {
          const el = document.getElementById(firstErrorField);
          el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
          el?.focus();
        }, 100);
      }

      return Object.keys(newErrors).length === 0;
    },
    []
  );

  const clearError = useCallback((field: string) => {
    setErrors((prev) => {
      const next = { ...prev };
      delete next[field];
      return next;
    });
  }, []);

  const clearAllErrors = useCallback(() => {
    setErrors({});
  }, []);

  return { errors, validate, clearError, clearAllErrors };
}
