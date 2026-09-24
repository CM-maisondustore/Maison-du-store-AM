/* ==========================================================================
   utils/validation.js
   Fonctions génériques de validation, réutilisables par tous les modules :
   e-mail, téléphone, champ obligatoire, nombre positif, texte.
   Aucune dépendance au DOM : chaque fonction renvoie un booléen.
   Quelques utilitaires de texte (normalisation, échappement) sont ajoutés
   car tous les modules qui affichent une saisie en ont besoin.
   ========================================================================== */
(function (MDS) {
  'use strict';

  /** Messages d'erreur par défaut (français). */
  const messages = {
    required: 'Ce champ est obligatoire.',
    email: 'Saisissez une adresse e-mail valide, par exemple nom@domaine.com.',
    phone: 'Saisissez un numéro de téléphone valide, par exemple +225 07 12 34 56 78.',
    positive: 'Saisissez un nombre supérieur à 0.',
    text: 'Ce texte est trop court, trop long ou contient des caractères non autorisés.'
  };

  /** Vrai si la valeur n'est ni vide ni composée uniquement d'espaces. */
  function isRequired(value) {
    if (value === null || value === undefined) return false;
    if (Array.isArray(value)) return value.length > 0;
    return String(value).trim() !== '';
  }

  /** Adresse e-mail : forme générale nom@domaine.ext (pas de contrôle RFC exhaustif). */
  function isEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(value || '').trim());
  }

  /**
   * Numéro de téléphone : espaces, points, tirets et parenthèses sont ignorés.
   * Accepte 8 à 15 chiffres, avec + ou 00 facultatif (format international).
   */
  function isPhone(value) {
    const cleaned = String(value || '').replace(/[\s.\-()]/g, '');
    return /^(\+|00)?\d{8,15}$/.test(cleaned);
  }

  /** Convertit "12,5" ou "12.5" en nombre ; NaN si ce n'est pas un nombre. */
  function toNumber(value) {
    if (typeof value === 'number') return value;
    const normalized = String(value === null || value === undefined ? '' : value).trim().replace(',', '.');
    if (normalized === '') return NaN;
    return Number(normalized);
  }

  /**
   * Nombre strictement positif.
   * options : { min, max, integer }
   */
  function isPositiveNumber(value, options) {
    const opts = options || {};
    const n = toNumber(value);
    if (!Number.isFinite(n) || n <= 0) return false;
    if (typeof opts.min === 'number' && n < opts.min) return false;
    if (typeof opts.max === 'number' && n > opts.max) return false;
    if (opts.integer && !Number.isInteger(n)) return false;
    return true;
  }

  /**
   * Texte libre : longueur comprise entre min et max, sans balise HTML.
   * options : { min = 1, max = 200 }
   */
  function isValidText(value, options) {
    const opts = Object.assign({ min: 1, max: 200 }, options);
    const text = String(value === null || value === undefined ? '' : value).trim();
    if (text.length < opts.min || text.length > opts.max) return false;
    return !/[<>]/.test(text);
  }

  /** Minuscules, sans accents ni ligatures : sert aux comparaisons et à la recherche. */
  function normalizeText(text) {
    return String(text === null || text === undefined ? '' : text)
      .toLowerCase()
      .replace(/œ/g, 'oe')
      .replace(/æ/g, 'ae')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, ' ')
      .trim();
  }

  /** Échappe les caractères HTML avant insertion dans un gabarit. */
  function escapeHtml(text) {
    return String(text === null || text === undefined ? '' : text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  MDS.validation = {
    messages,
    isRequired,
    isEmail,
    isPhone,
    isPositiveNumber,
    isValidText,
    toNumber,
    normalizeText,
    escapeHtml
  };
})((window.MDS = window.MDS || {}));
