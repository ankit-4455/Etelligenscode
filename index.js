
// @flow

// REACT
import React from 'react';

// MODULES
import { createStackNavigator } from 'react-navigation-stack';
import { createBottomTabNavigator } from 'react-navigation-tabs';

// CONTAINERS
import HomeContainer from 'src/containers/main/home';
import Post from "src/containers/post/post";
import SettingsContainer from 'src/containers/main/settings/menu';
import TwinsContainer from './twinsTabs';
import ChatsContainer from './chatTabs';
import TeachTopTabs from '../teachTopTabs';
import LearnTopTabs from '../learnTopTabs';

// COMPONENTS
import MainTabBar from './layouts/tabBar';
import ArrowBack from 'src/components/common/arrowBack';

// STYLES
import HeaderStyle from 'src/assets/styles/header';

const Home = createStackNavigator({
  HomeContainer,
  Post: {
    screen: Post,
    navigationOptions: {
      ...HeaderStyle,
      headerLeft: () => <ArrowBack />,
    }
  }
});

// Bottom Tabs 
const Twins = TwinsContainer;
const Teach = TeachTopTabs;
const Learn = LearnTopTabs;
const Chat = ChatsContainer;
//Use to create stack
const Settings = createStackNavigator({ SettingsContainer });

const Tabs = createBottomTabNavigator({
  Twins,
  Teach,
  Home,
  Learn,
  Chat,
  Settings,
}, {
  tabBarComponent: MainTabBar,
  initialRouteName: "Home",
});

export default Tabs;
