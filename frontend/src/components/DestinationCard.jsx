import { Link } from 'react-router-dom'
import { cardImage, placeholderStyle } from '../imagery'

export default function DestinationCard({ destination: d }) {
  const image = cardImage(d)

  return (
    <Link
      to={`/destinations/${d.id}`}
      className="overflow-hidden rounded-lg bg-white shadow hover:shadow-md"
    >
      {image ? (
        <img src={image} alt={d.name} loading="lazy" className="h-36 w-full object-cover" />
      ) : (
        <div className="flex h-36 items-end p-3" style={placeholderStyle(d.id)}>
          <span className="text-xl font-bold text-white drop-shadow">{d.name}</span>
        </div>
      )}

      <div className="p-4">
        <h2 className="text-lg font-semibold">{d.name}</h2>
        <p className="text-sm text-gray-500">{d.country} · {d.region}</p>
        <p className="mt-2 line-clamp-2 text-sm text-gray-700">{d.overview}</p>
        <div className="mt-3 flex flex-wrap gap-1">
          {d.themes.slice(0, 4).map((t) => (
            <span key={t} className="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700">{t}</span>
          ))}
        </div>
        <p className="mt-3 text-xs text-gray-500">
          {d.budget_level} · safety {d.safety_rating}/5
        </p>
      </div>
    </Link>
  )
}