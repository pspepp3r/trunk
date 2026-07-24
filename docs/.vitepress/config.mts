import { defineConfig } from "vitepress";

export default defineConfig({
	title: "Trunk",
	description: "An async, API-centric PHP framework built on ReactPHP",
	lang: "en-US",
	head: [["link", { rel: "icon", href: "/logo.svg" }]],

	themeConfig: {
		logo: "/logo.svg",

		nav: [{ text: "Guide", link: "/guide/getting-started" }],

		sidebar: [
			{
				text: "Guide",
				items: [
					{ text: "Getting Started", link: "/guide/getting-started" },
					{ text: "Routing", link: "/guide/routing" },
					{ text: "Middleware", link: "/guide/middleware" },
					{ text: "Validation", link: "/guide/validation" },
					{ text: "Database & Migrations", link: "/guide/database" },
					{ text: "Authentication", link: "/guide/authentication" },
					{ text: "Events", link: "/guide/events" },
					{ text: "GraphQL", link: "/guide/graphql" },
					{ text: "gRPC Client", link: "/guide/grpc" },
					{ text: "Console (CLI)", link: "/guide/console" },
					{ text: "Testing", link: "/guide/testing" },
				],
			},
		],

		socialLinks: [
			{ icon: "github", link: "https://github.com/pspepp3r/trunk" },
		],

		search: {
			provider: "local",
		},
	},
});
