import { Link } from 'react-router-dom'
import RecommendationsPanel from '../components/RecommendationsPanel'

export default function Recommendations() {
  return (
    <div className="mx-auto max-w-3xl p-6">
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-3xl font-bold">Recommended for you</h1>
        <div className="flex gap-4 text-blue-600">
          <Link to="/preferences">Edit interests</Link>
          <Link to="/">Dashboard</Link>
        </div>
      </div>
      <RecommendationsPanel />
    </div>
  )
}