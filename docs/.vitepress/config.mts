import { defineConfig } from "vitepress";

export default defineConfig({
	title: "Trunk",
	description: "An async, API-centric PHP framework built on ReactPHP",
	lang: "en-US",
	head: [["link", { rel: "icon", href: "/logo.svg" }]],

	themeConfig: {
		logo: "/logo.svg",

		nav: [
			{ text: "Tutorial", link: "/tutorial/introduction" },
			{ text: "Reference", link: "/guide/routing" },
		],

		sidebar: [
			{
				text: "Tutorial",
				items: [
					{ text: "Introduction", link: "/tutorial/introduction" },
					{ text: "Installation & Your First Route", link: "/tutorial/installation" },
					{ text: "Building a Resource", link: "/tutorial/building-a-resource" },
					{ text: "Adding Authentication", link: "/tutorial/authentication" },
				],
			},
			{
				text: "Reference",
				items: [
					{ text: "Routing", link: "/guide/routing" },
					{ text: "Middleware", link: "/guide/middleware" },
					{ text: "Validation", link: "/guide/validation" },
					{ text: "Database & Migrations", link: "/guide/database" },
					{ text: "Cache", link: "/guide/cache" },
					{ text: "Authentication", link: "/guide/authentication" },
					{ text: "Events", link: "/guide/events" },
					{ text: "GraphQL", link: "/guide/graphql" },
					{ text: "gRPC Client", link: "/guide/grpc" },
					{ text: "Console (CLI)", link: "/guide/console" },
					{ text: "Testing", link: "/guide/testing" },
					{ text: "Dependency Injection Container", link: "/guide/container" },
					{ text: "Service Providers", link: "/guide/service-providers" },
					{ text: "Configuration", link: "/guide/configuration" },
					{ text: "Logging", link: "/guide/logging" },
					{ text: "Sessions", link: "/guide/sessions" },
					{ text: "Helpers", link: "/guide/helpers" },
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
