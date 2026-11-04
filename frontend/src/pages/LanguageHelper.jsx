import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api, { errorMessage } from '../api'
import { useAuth } from '../AuthContext'

const inputClass = 'mt-1 w-full rounded border px-3 py-2'

const CATEGORY_LABELS = {
  greetings: 'Greetings',
  food: 'Food',
  directions: 'Directions',
  emergencies: 'Emergencies',
}

// Browser voices for the listen button (Web Speech API, no external service).
const VOICES = {
  es: 'es-ES', fr: 'fr-FR', de: 'de-DE', it: 'it-IT', id: 'id-ID',
  ar: 'ar-SA', zh: 'zh-CN', ja: 'ja-JP', th: 'th-TH', tr: 'tr-TR',
}

function speak(text, languageCode) {
  if (!('speechSynthesis' in window)) return
  window.speechSynthesis.cancel()
  const utterance = new SpeechSynthesisUtterance(text)
  utterance.lang = VOICES[languageCode] || languageCode
  utterance.rate = 0.85
  window.speechSynthesis.speak(utterance)
}

function PhraseRow({ phrase, showLanguage, canFavorite, onToggle }) {
  return (
    <li className="flex items-start justify-between gap-3 py-3">
      <div>
        <p className="text-sm text-gray-500">
          {phrase.english}
          {showLanguage ? ` · ${phrase.language_name}` : ''}
        </p>
        <p className="text-lg font-semibold" lang={phrase.language_code} dir={phrase.language_code === 'ar' ? 'rtl' : 'ltr'}>
          {phrase.translation}
        </p>
        {phrase.romanization && <p className="text-sm">{phrase.romanization}</p>}
        <p className="text-sm text-blue-700">Say: {phrase.pronunciation}</p>
      </div>
      <div className="flex gap-1">
        <button
          className="rounded border px-2 py-1 text-sm"
          title="Listen"
          aria-label={`Listen to ${phrase.english}`}
          onClick={() => speak(phrase.translation, phrase.language_code)}
        >
          🔊
        </button>
        {canFavorite && (
          <button
            className={`rounded border px-2 py-1 text-sm ${phrase.is_favorite ? 'text-amber-600' : 'text-gray-500'}`}
            aria-pressed={phrase.is_favorite}
            aria-label={phrase.is_favorite ? 'Remove from favorites' : 'Save to favorites'}
            onClick={() => onToggle(phrase)}
          >
            {phrase.is_favorite ? '★' : '☆'}
          </button>
        )}
      </div>
    </li>
  )
}

export default function LanguageHelper() {
  const { user } = useAuth()
  const [languages, setLanguages] = useState([])
  const [countries, setCountries] = useState([])
  const [language, setLanguage] = useState('ja')
  const [country, setCountry] = useState('Japan')
  const [category, setCategory] = useState('all')
  const [search, setSearch] = useState('')
  const [groups, setGroups] = useState({})
  const [tips, setTips] = useState(null)
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)

  useEffect(() => {
    Promise.all([api.get('/phrases/languages'), api.get('/cultural-tips/countries')])
      .then(([languageRes, countryRes]) => {
        setLanguages(languageRes.data.languages)
        setCountries(countryRes.data)
      })
      .catch((err) => setError(errorMessage(err)))
  }, [])

  useEffect(() => {
    let ignore = false
    const request =
      category === 'favorites'
        ? api.get('/phrases/favorites').then((res) => (res.data.length ? { favorites: res.data } : {}))
        : api
            .get('/phrases', {
              params: { language, category: category === 'all' ? undefined : category, q: search || undefined },
            })
            .then((res) => res.data)

    request
      .then((data) => {
        if (!ignore) setGroups(data)
      })
      .catch((err) => {
        if (!ignore) setError(errorMessage(err))
      })

    return () => {
      ignore = true
    }
  }, [language, category, search, reload])

  useEffect(() => {
    let ignore = false

    api
      .get('/cultural-tips', { params: { country } })
      .then((res) => {
        if (!ignore) setTips(res.data)
      })
      .catch(() => {
        if (!ignore) setTips(null)
      })

    return () => {
      ignore = true
    }
  }, [country])

  function chooseLanguage(code) {
    setLanguage(code)
    const match = countries.find((c) => c.language_code === code)
    if (match) setCountry(match.country)
  }

  async function toggleFavorite(phrase) {
    try {
      if (phrase.is_favorite) {
        await api.delete(`/phrases/${phrase.id}/favorite`)
      } else {
        await api.post(`/phrases/${phrase.id}/favorite`)
      }
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  const tabs = ['all', ...Object.keys(CATEGORY_LABELS), ...(user ? ['favorites'] : [])]
  const phrases = Object.values(groups).flat()

  return (
    <div className="mx-auto max-w-5xl p-6">
      <h1 className="text-3xl font-bold">Phrasebook and cultural tips</h1>
      <p className="text-gray-600">Useful phrases in 10 languages with pronunciation, and etiquette for each country.</p>

      {error && <p className="mt-4 rounded bg-red-50 p-2 text-red-700">{error}</p>}

      <div className="mt-6 grid gap-6 md:grid-cols-2">
        <section className="rounded-lg bg-white p-4 shadow">
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="text-sm">
              Language
              <select className={inputClass} value={language} onChange={(e) => chooseLanguage(e.target.value)}>
                {languages.map((l) => (
                  <option key={l.language_code} value={l.language_code}>{l.language_name}</option>
                ))}
              </select>
            </label>
            <label className="text-sm">
              Search
              <input
                className={inputClass}
                type="search"
                placeholder="e.g. hospital, bill"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </label>
          </div>

          <div className="mt-4 flex flex-wrap gap-1 border-b text-sm">
            {tabs.map((tab) => (
              <button
                key={tab}
                className={`px-3 py-2 ${category === tab ? 'border-b-2 border-blue-700 font-semibold text-blue-700' : 'text-gray-600'}`}
                onClick={() => setCategory(tab)}
              >
                {tab === 'all' ? 'All' : tab === 'favorites' ? '★ Favorites' : CATEGORY_LABELS[tab]}
              </button>
            ))}
          </div>

          {phrases.length === 0 ? (
            <p className="py-6 text-center text-gray-600">
              {category === 'favorites' ? 'No favorites yet. Tap ☆ on a phrase to save it.' : 'No phrases match.'}
            </p>
          ) : (
            Object.entries(groups).map(([group, items]) => (
              <div key={group}>
                {category === 'all' && <h2 className="mt-4 font-semibold">{CATEGORY_LABELS[group]}</h2>}
                <ul className="divide-y">
                  {items.map((phrase) => (
                    <PhraseRow
                      key={phrase.id}
                      phrase={phrase}
                      showLanguage={category === 'favorites'}
                      canFavorite={Boolean(user)}
                      onToggle={toggleFavorite}
                    />
                  ))}
                </ul>
              </div>
            ))
          )}

          {!user && (
            <p className="mt-3 text-sm text-gray-600">
              <Link className="text-blue-600" to="/login">Log in</Link> to save favorite phrases.
            </p>
          )}
          <p className="mt-3 text-sm text-gray-500">Machine translation is planned for a later version.</p>
        </section>

        <section>
          <div className="rounded-lg bg-white p-4 shadow">
            <label className="text-sm">
              Etiquette for
              <select className={inputClass} value={country} onChange={(e) => setCountry(e.target.value)}>
                {countries.map((c) => (
                  <option key={c.country} value={c.country}>{c.country}</option>
                ))}
              </select>
            </label>
          </div>
          <ul className="mt-4 space-y-4">
            {tips?.topics.map((topic) => (
              <li key={topic.key} className="rounded-lg bg-white p-4 shadow">
                <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">{topic.label}</p>
                {topic.tips.map((tip) => (
                  <div key={tip.id}>
                    <h3 className="mt-1 font-semibold">{tip.title}</h3>
                    <p className="text-sm text-gray-700">{tip.body}</p>
                  </div>
                ))}
              </li>
            ))}
          </ul>
        </section>
      </div>
    </div>
  )
}
