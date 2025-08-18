import { createBrowserRouter } from 'react-router-dom';
import PublicLayout from "../layouts/PublicLayout.jsx";
import Login from '../pages/Login/Login.jsx';
import App from '../App.jsx';
import NotFound from '../pages/NotFound/notfound.jsx';
import Dashboard from '../pages/Dashboard/Dashboard.jsx';
import Analysis from '../pages/Analysis/Analysis.jsx';
import Planning from '../pages/Planning/Planning.jsx';
import Goals from '../pages/Goals/Goals.jsx';
import Patrimony from '../pages/Patrimony/Patrimony.jsx';
import Manage from '../pages/Manage/Manage.jsx';

export const router = createBrowserRouter([
	// Rotas públicas (sem Sidebar)
	{
		element: <PublicLayout />,
		errorElement: <NotFound />,
		children: [
			{
				path: "/login",
				element: <Login />
			}
		]
	},

	// Rotas privadas (com Sidebar e layout App)
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
