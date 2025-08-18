import { createBrowserRouter } from 'react-router-dom';
import App from '../App.jsx';
import NotFound from '../pages/NotFound/notfound.jsx';
import Dashboard from '../pages/Dashboard/Dashboard.jsx';
import Analysis from '../pages/Analysis/Analysis.jsx';
import Planning from '../pages/Planning/Planning.jsx';
import Goals from '../pages/Goals/Goals.jsx';
import Patrimony from '../pages/Patrimony/Patrimony.jsx';
import Manage from '../pages/Manage/Manage.jsx';

export const router = createBrowserRouter([
	{
		path: '/',
		element: <App />,
		errorElement: <NotFound />,
		children: [
			{
				path: '/',
				element: <Dashboard />
			},
			{
				path: '/analysis',
				element: <Analysis />
			},
			{
				path: '/planning',
				element: <Planning />
			},
			{
				path: '/goals',
				element: <Goals />
			},
			{
				path: '/patrimony',
				element: <Patrimony />
			},
			{
				path: '/manage',
				element: <Manage />
			}
		]
	}
])
